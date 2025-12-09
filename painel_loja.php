<?php
include 'db_connect.php'; 
session_start();

// 1. VERIFICAÇÃO DE SESSÃO DA LOJA
if (!isset($_SESSION['logado']) || $_SESSION['tipo_usuario'] !== 'loja') {
    header("Location: login_loja.php"); 
    exit();
}

$cd_loja = $_SESSION['cd_usuario']; 
$nm_loja = $_SESSION['nm_usuario'];
$aba_ativa = isset($_GET['aba']) ? $_GET['aba'] : 'dashboard'; 
$mensagem_config = ''; // Para exibir mensagens de sucesso ou erro na aba de configurações

// 1.1. TRATAMENTO DE MENSAGENS DE SESSÃO (APÓS AÇÕES COMO EXCLUSÃO OU STATUS)
$mensagem_sessao = '';
if (isset($_SESSION['mensagem_loja'])) {
    $mensagem_sessao = $_SESSION['mensagem_loja'];
    unset($_SESSION['mensagem_loja']); // Limpa a mensagem após exibir
}

// 2. LÓGICA DE ATUALIZAÇÃO DE DADOS DA LOJA (Configurações)
if ($aba_ativa == 'configuracoes' && isset($_POST['atualizar_dados'])) {
    
    $nm_loja_novo = $mysqli->real_escape_string(trim($_POST['nm_loja']));
    $nm_razao_social = $mysqli->real_escape_string(trim($_POST['nm_razao_social']));
    $nr_telefone = $mysqli->real_escape_string(trim($_POST['nr_telefone']));
    
    $sql = "UPDATE cadastro_loja SET nm_loja = ?, nm_razao_social = ?, nr_telefone = ? WHERE cd_loja = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("sssi", $nm_loja_novo, $nm_razao_social, $nr_telefone, $cd_loja);
    
    if ($stmt->execute()) {
        $_SESSION['nm_usuario'] = $nm_loja_novo; // Atualiza a sessão
        $mensagem_config = "<p class='msg-sucesso'>✅ Dados da loja atualizados com sucesso!</p>";
    } else {
        $mensagem_config = "<p class='msg-erro'>❌ Erro ao atualizar os dados: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

// 3. BUSCA DADOS ATUAIS DA LOJA (para o sidebar e formulário de config)
$dados_loja = [];
if ($stmt_dados = $mysqli->prepare("SELECT nm_razao_social, ds_email, nr_telefone FROM cadastro_loja WHERE cd_loja = ?")) {
    $stmt_dados->bind_param("i", $cd_loja);
    $stmt_dados->execute();
    $resultado_dados = $stmt_dados->get_result();
    $dados_loja = $resultado_dados->fetch_assoc();
    $stmt_dados->close();
}


// =========================================================
// 4. LÓGICA DE AÇÕES DE PEDIDO (PENDENTE, ENTREGUE, EM PREPARAÇÃO)
//    (CORREÇÃO INTEGRADA: Lógica robusta para mudança de status e redirecionamento)
// =========================================================
if (isset($_POST['acao_pedido'])) {
    $cd_pedido_alvo = isset($_POST['cd_pedido']) ? intval($_POST['cd_pedido']) : 0;
    $nova_status = $mysqli->real_escape_string($_POST['nova_status']);
    
    // Statuses permitidos para mudança pela loja (Corrigido conforme pedido)
    $status_permitidos = ['Processando', 'Em Entrega', 'Entregue']; 

    if ($cd_pedido_alvo > 0 && in_array($nova_status, $status_permitidos)) {
        
        // 1. Atualiza o status do pedido
        $sql = "UPDATE pedido SET ds_status_pedido = ?, status_pedido = NOW() WHERE cd_pedido = ? AND cd_loja = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sii", $nova_status, $cd_pedido_alvo, $cd_loja);
        
        if ($stmt->execute()) {
            if ($nova_status === 'Entregue') {
                $_SESSION['mensagem_loja'] = "<p class='msg-sucesso'>✅ Pedido #{$cd_pedido_alvo} FINALIZADO! O valor foi adicionado ao faturamento.</p>";
            } else {
                $_SESSION['mensagem_loja'] = "<p class='msg-sucesso'>✅ Pedido #{$cd_pedido_alvo} atualizado para '{$nova_status}'.</p>";
            }
        } else {
            $_SESSION['mensagem_loja'] = "<p class='msg-erro'>❌ Erro ao atualizar pedido: " . $stmt->error . "</p>";
        }
        $stmt->close();
        
        // Redireciona para evitar re-submissão e manter na aba atual
        $redirect_aba = isset($_GET['aba']) ? $_GET['aba'] : 'dashboard'; 
        header("Location: painel_loja.php?aba={$redirect_aba}");
        exit();
    }
}


// =========================================================
// 5. FUNÇÕES DE CARREGAMENTO DE CONTEÚDO
// =========================================================

/**
 * Função principal para determinar qual conteúdo carregar.
 */
function carregar_conteudo($aba, $mysqli, $cd_loja, $nm_loja, $dados_loja, $mensagem_config, $mensagem_sessao) {
    switch ($aba) {
        case 'dashboard':
            return carregar_conteudo_dashboard($mysqli, $cd_loja);
        case 'pedidos':
            return carregar_conteudo_pedidos($mysqli, $cd_loja);
        case 'produtos':
            return carregar_conteudo_produtos($mysqli, $cd_loja);
        case 'configuracoes':
            return carregar_conteudo_configuracoes($nm_loja, $dados_loja, $mensagem_config);
        default:
            return "<p class='msg-aviso'>Página não encontrada.</p>";
    }
}

// ---------------------------------------------------------
// 5.1. FUNÇÃO: carregar_conteudo_dashboard (CORRIGIDA)
// ---------------------------------------------------------

/**
 * Busca e exibe as métricas e pedidos recentes (Dashboard).
 */
function carregar_conteudo_dashboard($mysqli, $cd_loja) {
    global $mensagem_sessao; 
    
    $total_pedidos = 0;
    $faturamento_total = 0.00;
    $pedidos_pendentes = 0;
    $pedidos_recentes = [];

    // Array de Status para o Dropdown (OPÇÕES SOLICITADAS: sem 'Aguardando Pagamento' e 'Cancelado')
    $opcoes_dashboard = [
        'Processando' => 'Em Preparação (Sendo Feito)',
        'Em Entrega' => 'Em Entrega (Pendente)',
        'Entregue' => 'Concluído (Entregue)',
    ];

    // Lógica para Contagens e Soma: Faturamento soma 'Entregue'. Pendentes são Processando/Aguardando/Em Entrega.
    $sql_metrics = "
        SELECT 
            COUNT(cd_pedido) AS total_pedidos,
            SUM(CASE WHEN ds_status_pedido = 'Entregue' THEN vl_total ELSE 0 END) AS faturamento_total,
            SUM(CASE WHEN ds_status_pedido IN ('Processando', 'Aguardando Pagamento', 'Em Entrega') THEN 1 ELSE 0 END) AS pedidos_pendentes
        FROM pedido 
        WHERE cd_loja = ? AND ds_status_pedido != 'Carrinho'
    ";
    if ($stmt_metrics = $mysqli->prepare($sql_metrics)) {
        $stmt_metrics->bind_param("i", $cd_loja);
        $stmt_metrics->execute();
        $res_metrics = $stmt_metrics->get_result()->fetch_assoc();
        
        $total_pedidos = intval($res_metrics['total_pedidos'] ?? 0);
        $faturamento_total = floatval($res_metrics['faturamento_total'] ?? 0.00);
        $pedidos_pendentes = intval($res_metrics['pedidos_pendentes'] ?? 0); 
        
        $stmt_metrics->close();
    }

    // Pedidos Recentes: Filtra pedidos Concluídos ('Entregue') e Cancelados (para que o lojista só veja o que precisa de ação)
    $sql_recentes = "
        SELECT 
            p.cd_pedido, 
            c.nm_cliente, 
            p.ds_status_pedido, 
            p.dt_pedido,
            p.vl_total
        FROM pedido p
        JOIN cadastro_cliente c ON p.cd_cliente = c.cd_cliente
        WHERE p.cd_loja = ? 
          AND p.ds_status_pedido NOT IN ('Carrinho', 'Entregue', 'Cancelado') 
        ORDER BY p.dt_pedido DESC
        LIMIT 5
    ";
    if ($stmt_recentes = $mysqli->prepare($sql_recentes)) {
        $stmt_recentes->bind_param("i", $cd_loja);
        $stmt_recentes->execute();
        $resultado_recentes = $stmt_recentes->get_result();
        while ($pedido = $resultado_recentes->fetch_assoc()) {
            $pedidos_recentes[] = $pedido;
        }
        $stmt_recentes->close();
    }

    $html = "<div class='dashboard-content'>";
    
    // Exibe mensagens de sessão se houver
    if (!empty($mensagem_sessao)) {
        $html .= $mensagem_sessao;
    }
    
    // CARDS DE MÉTRICAS 
    $html .= "
        <p class='dash-subtitle'>Visão geral rápida das suas vendas.</p>
        <section class='dashboard-grid'>
            <div class='dashboard-card total-pedidos scroll-fade-up' data-delay='0.1'>
                <h3>Total de Pedidos</h3>
                <span class='data-value'>{$total_pedidos}</span>
                <p>Pedidos concluídos</p>
            </div>
            
            <div class='dashboard-card faturamento scroll-fade-up' data-delay='0.2'>
                <h3>Faturamento Bruto</h3>
                <span class='data-value'>R$ " . number_format($faturamento_total, 2, ',', '.') . "</span>
                <p>Faturamento Total</p>
            </div>
            
            <div class='dashboard-card pendentes scroll-fade-up metric-alert' data-delay='0.3'>
                <h3>Pedidos Pendentes</h3>
                <span class='data-value'>{$pedidos_pendentes}</span>
                <p>Aguardando preparo</p>
            </div>
        </section>
    ";

    // TABELA DE PEDIDOS RECENTES
    $html .= "
        <h3>Pedidos Recentes (Máximo 5 - Pedidos em Andamento)</h3>
        <div class='table-responsive scroll-fade-up' data-delay='0.4'>
            <table class='inventory-table'>
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Cliente</th>
                        <th>Data</th>
                        <th>Valor Total</th>
                        <th>Status / Ação</th> 
                    </tr>
                </thead>
                <tbody>
    ";
    
    if (empty($pedidos_recentes)) {
        $html .= "<tr><td colspan='5'>Nenhum pedido em andamento nos recentes.</td></tr>";
    } else {
        foreach ($pedidos_recentes as $pedido) {
            $status_atual = htmlspecialchars($pedido['ds_status_pedido']);
            $cd_pedido = $pedido['cd_pedido'];

            $html .= "
                <tr>
                    <td>" . htmlspecialchars($cd_pedido) . "</td>
                    <td>" . htmlspecialchars($pedido['nm_cliente']) . "</td>
                    <td>" . date('d/m/Y H:i', strtotime($pedido['dt_pedido'])) . "</td>
                    <td>R$ " . number_format($pedido['vl_total'], 2, ',', '.') . "</td>
                    
                    <td>
                        <form method='POST' style='display: flex; align-items: center; gap: 5px;'>
                            <input type='hidden' name='acao_pedido' value='1'>
                            <input type='hidden' name='cd_pedido' value='{$cd_pedido}'>
                            
                            <select name='nova_status' class='select-status-dash'>
            ";
            
            // Popula o Dropdown (Apenas Processando, Em Entrega, Entregue)
            foreach ($opcoes_dashboard as $valor => $descricao) {
                $selected = ($valor == $status_atual) ? 'selected' : '';
                
                $html .= "<option value='{$valor}' {$selected}>{$descricao}</option>";
            }
            
            $html .= "
                            </select>
                            
                            <button type='submit' class='btn-salvar-status' title='Salvar Status'>
                                &#x2714;
                            </button>
                        </form>
                    </td>
                    </tr>
            ";
        }
    }
    
    $html .= "
                </tbody>
            </table>
        </div>
        <div class='dashboard-actions scroll-fade-up' data-delay='0.5'>
            <a href='painel_loja.php?aba=pedidos' class='btn-acao-secundaria'>Ver Todos os Pedidos Pendentes</a>
        </div>
    ";
    
    $html .= "</div>";
    return $html;
}

// ---------------------------------------------------------
// 5.2. FUNÇÃO: carregar_conteudo_pedidos (CORRIGIDA)
// ---------------------------------------------------------

/**
 * Exibe a aba de Pedidos (PENDENTES) com Dropdown e Botões de Ação.
 */
function carregar_conteudo_pedidos($mysqli, $cd_loja) {
    global $mensagem_sessao;
    
    // Array de Status para o Dropdown (OPÇÕES SOLICITADAS)
    $opcoes_pedidos = [
        'Processando' => 'Em Preparação (Sendo Feito)',
        'Em Entrega' => 'Em Entrega (Pendente)',
        'Entregue' => 'Concluído (Entregue)',
    ];

    // Busca pedidos pendentes (não concluídos e não cancelados)
    $sql = "
        SELECT 
            p.cd_pedido, 
            c.nm_cliente, 
            p.ds_status_pedido, 
            p.dt_pedido,
            p.vl_total,
            ec.nm_cidade,
            ec.nm_bairro,
            ec.ds_endereco
        FROM pedido p
        JOIN cadastro_cliente c ON p.cd_cliente = c.cd_cliente
        LEFT JOIN endereco_cliente ec ON p.cd_endereco = ec.cd_endereco
        WHERE p.cd_loja = ? 
          AND p.ds_status_pedido NOT IN ('Carrinho', 'Entregue', 'Cancelado')
        ORDER BY p.dt_pedido ASC
    ";
    
    $pedidos = [];
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $cd_loja);
        $stmt->execute();
        $resultado = $stmt->get_result();
        while ($pedido = $resultado->fetch_assoc()) {
            $pedidos[] = $pedido;
        }
        $stmt->close();
    }
    
    $html = "<div class='pedidos-content'>";
    
    if (!empty($mensagem_sessao)) {
        $html .= $mensagem_sessao;
    }
    
    $html .= "<p class='dash-subtitle'>Aqui estão os pedidos que exigem sua atenção (Em Preparação, Em Entrega ou Aguardando Pagamento).</p>";
    
    if (empty($pedidos)) {
        $html .= "<p class='msg-sucesso'>🎉 Não há pedidos pendentes no momento! Ótimo trabalho!</p>";
    } else {
        foreach ($pedidos as $pedido) {
            $status_atual = htmlspecialchars($pedido['ds_status_pedido']);
            $status_class = strtolower(str_replace(' ', '-', $status_atual));
            $cd_pedido = $pedido['cd_pedido'];
            
            $html .= "
                <div class='pedido-card scroll-fade-up' data-id='{$cd_pedido}'>
                    <div class='pedido-header'>
                        <h4>Pedido #{$cd_pedido} - Cliente: " . htmlspecialchars($pedido['nm_cliente']) . "</h4>
                        <span class='status-badge status-{$status_class}'>{$status_atual}</span>
                    </div>
                    
                    <div class='pedido-details'>
                        <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i', strtotime($pedido['dt_pedido'])) . "</p>
                        <p><strong>Total:</strong> R$ " . number_format($pedido['vl_total'], 2, ',', '.') . "</p>
                        <p><strong>Entrega:</strong> " . htmlspecialchars($pedido['ds_endereco'] ?? '') . ", " . htmlspecialchars($pedido['nm_bairro'] ?? '') . " - " . htmlspecialchars($pedido['nm_cidade'] ?? '') . "</p>
                    </div>
                    
                    <div class='pedido-actions'>
                        <form method='POST' style='display: inline-flex; align-items: center; gap: 10px; margin-right: 20px;'>
                            <input type='hidden' name='acao_pedido' value='1'>
                            <input type='hidden' name='cd_pedido' value='{$cd_pedido}'>
                            
                            <label for='status_{$cd_pedido}' style='font-weight: 600;'>Mudar Status:</label>
                            <select id='status_{$cd_pedido}' name='nova_status' class='select-status'>
            ";
            
            // Popula o Dropdown (Apenas Processando, Em Entrega, Entregue)
            foreach ($opcoes_pedidos as $valor => $descricao) {
                $selected = ($valor == $status_atual) ? 'selected' : '';
                
                $html .= "<option value='{$valor}' {$selected}>{$descricao}</option>";
            }
            
            $html .= "
                            </select>
                            <button type='submit' class='btn-salvar-status' title='Salvar Status'>
                                &#x2714;
                            </button>
                        </form>

                        <form method='POST' style='display: inline-block;'>
                            <input type='hidden' name='acao_pedido' value='1'>
                            <input type='hidden' name='cd_pedido' value='{$cd_pedido}'>
                            <input type='hidden' name='nova_status' value='Entregue'>
                            <button type='submit' class='btn-pedido-action btn-entregue' title='Finalizar Pedido e Adicionar ao Faturamento'>
                                ✅ Concluir e Somar ao Faturamento
                            </button>
                        </form>
                        
                    </div>
                </div>
            ";
        }
    }
    
    $html .= "</div>";
    return $html;
}

// ---------------------------------------------------------
// 5.3. FUNÇÃO: carregar_conteudo_produtos
// ---------------------------------------------------------

/**
 * Exibe a aba de Gerenciamento de Produtos.
 */
function carregar_conteudo_produtos($mysqli, $cd_loja) {
    global $mensagem_sessao; 
    
    $produtos = [];
    $sql = "SELECT cd_produto, nm_produto, vl_preco, qt_estoque, ds_imagem FROM produto WHERE cd_loja = ? ORDER BY cd_produto DESC";
    
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $cd_loja);
        $stmt->execute();
        $resultado = $stmt->get_result();
        while ($produto = $resultado->fetch_assoc()) {
            $produtos[] = $produto;
        }
        $stmt->close();
    }
    
    $html = "<div class='produtos-content'>";
    
    if (!empty($mensagem_sessao)) {
        $html .= $mensagem_sessao;
    }
    
    $html .= "
        <a href='cadastro_produto.php' class='btn-cadastro-produto scroll-fade-up'>+ Cadastrar Novo Produto</a>
        <p class='dash-subtitle'>Gerencie o estoque e os detalhes dos seus produtos.</p>
        
        <div class='table-responsive scroll-fade-up'>
            <table class='inventory-table'>
                <thead>
                    <tr>
                        <th>Imagem</th>
                        <th>#ID</th>
                        <th>Produto</th>
                        <th>Preço</th>
                        <th>Estoque</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
    ";
    
    if (empty($produtos)) {
        $html .= "<tr><td colspan='6'>Nenhum produto cadastrado.</td></tr>";
    } else {
        foreach ($produtos as $produto) {
            $ds_imagem = htmlspecialchars($produto['ds_imagem'] ?? 'placeholder.png'); // Imagem padrão se não houver
            $nm_produto = htmlspecialchars($produto['nm_produto']);
            $vl_preco = number_format($produto['vl_preco'], 2, ',', '.');
            $qt_estoque = htmlspecialchars($produto['qt_estoque']);
            $cd_produto = $produto['cd_produto'];
            
            // Adiciona classe de alerta se o estoque for baixo (ex: < 10)
            $estoque_class = ($qt_estoque < 10 && $qt_estoque > 0) ? 'estoque-baixo' : (($qt_estoque == 0) ? 'estoque-zero' : '');

            $html .= "
                <tr>
                    <td><img src='{$ds_imagem}' alt='{$nm_produto}' class='produto-miniatura'></td>
                    <td>{$cd_produto}</td>
                    <td>{$nm_produto}</td>
                    <td>R$ {$vl_preco}</td>
                    <td class='{$estoque_class}'>{$qt_estoque}</td>
                    <td>
                        <a href='cadastro_produto.php?action=edit&id={$cd_produto}' class='btn-acao btn-editar'>✏️ Editar</a>
                        <button onclick='confirmDelete({$cd_produto})' class='btn-acao btn-excluir'>🗑️ Excluir</button>
                    </td>
                </tr>
            ";
        }
    }
    
    $html .= "
                </tbody>
            </table>
        </div>
    </div>";
    return $html;
}

// ---------------------------------------------------------
// 5.4. FUNÇÃO: carregar_conteudo_configuracoes
// ---------------------------------------------------------

/**
 * Exibe o formulário de Configurações da Loja.
 */
function carregar_conteudo_configuracoes($nm_loja, $dados_loja, $mensagem_config) {
    $html = "<div class='configuracoes-content'>";
    
    if (!empty($mensagem_config)) {
        $html .= $mensagem_config;
    }
    
    $html .= "
        <p class='dash-subtitle'>Altere os dados básicos da sua loja e informações de contato.</p>

        <form method='POST' action='painel_loja.php?aba=configuracoes' class='config-form scroll-fade-up'>
            <input type='hidden' name='atualizar_dados' value='1'>

            <div class='form-group'>
                <label for='nm_loja'>Nome da Loja (Nome Fantasia):</label>
                <input type='text' id='nm_loja' name='nm_loja' value='" . htmlspecialchars($nm_loja) . "' required>
            </div>

            <div class='form-group'>
                <label for='nm_razao_social'>Razão Social/Nome Completo:</label>
                <input type='text' id='nm_razao_social' name='nm_razao_social' value='" . htmlspecialchars($dados_loja['nm_razao_social'] ?? '') . "' required>
            </div>

            <div class='form-group'>
                <label for='ds_email'>Email:</label>
                <input type='email' id='ds_email' name='ds_email' value='" . htmlspecialchars($dados_loja['ds_email'] ?? '') . "' disabled title='O email não pode ser alterado.'>
                <small class='text-muted'>Email é usado para login e não pode ser alterado.</small>
            </div>

            <div class='form-group'>
                <label for='nr_telefone'>Telefone de Contato (Ex: 13 1111-1111):</label>
                <input type='text' id='nr_telefone' name='nr_telefone' value='" . htmlspecialchars($dados_loja['nr_telefone'] ?? '') . "'>
            </div>
            
            <button type='submit' class='btn-salvar-config'>Salvar Alterações</button>
        </form>

        <div class='config-info scroll-fade-up'>
            <h4>Informações Importantes:</h4>
            <p><strong>Status da Loja:</strong> <span class='status-badge status-ativa'>Ativa</span></p>
            <p><strong>CNPJ/CPF:</strong> Apenas o suporte pode alterar seu documento.</p>
            <p><strong>Endereço:</strong> O endereço da loja é usado para calcular frete e deve ser atualizado via suporte, se necessário.</p>
        </div>

    </div>";

    
    return $html;
}


?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel da Loja - <?php echo $nm_loja; ?></title>
    <style>
        /* Variáveis de Cores (Paleta para tema claro com toque de marrom) */
        :root {
            --cor-principal: #FFD700; /* Dourado */
            --cor-secundaria: #F0E68C; /* Caqui Claro */
            --cor-marrom-acao: #964B00; /* Marrom para ações/links */
            --cor-marrom-escuro: #4B382D; /* Marrom Escuro para textos */
            --cor-fundo-claro: #fff6e9ff;
            --cor-borda: #DDD;
            --cor-sucesso: #28a745;
            --cor-erro: #dc3545;
            --cor-aviso: #ffc107;
            --cor-status-processando: #17a2b8;
            --cor-status-entregue: var(--cor-sucesso);
            --cor-status-entrega: var(--cor-aviso);
            --cor-status-aguardando: #6c757d;
        }

        /* Estilos Globais */
        body {
            font-family: 'Arial', sans-serif;
            background-color: var(--cor-fundo-claro);
            margin: 0;
            padding: 0;
            color: var(--cor-marrom-escuro);
        }

        .container-loja {
            display: flex;
            min-height: 100vh;
        }

        /* Navegação Lateral (Sidebar) */
        .sidebar-loja {
            width: 250px;
            background-color: white;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--cor-borda);
        }

        .sidebar-loja h3 {
            color: var(--cor-marrom-escuro);
            margin-top: 0;
            border-bottom: 2px solid var(--cor-borda);
            padding-bottom: 10px;
            font-size: 1.3em;
        }

        .sidebar-loja h4 {
            color: var(--cor-marrom-acao);
            margin-bottom: 25px;
            font-size: 1.1em;
        }
        
        .sidebar-loja ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-loja ul li a {
            display: block;
            padding: 10px 15px;
            text-decoration: none;
            color: var(--cor-marrom-escuro);
            border-radius: 8px;
            margin-bottom: 8px;
            transition: background-color 0.2s, color 0.2s;
            font-weight: 500;
        }

        .sidebar-loja ul li a.active,
        .sidebar-loja ul li a:hover {
            background-color: var(--cor-marrom-acao);
            color: white;
            font-weight: 600;
        }
        
        .sidebar-loja .logout-link {
            margin-top: auto;
            border-top: 1px solid var(--cor-borda);
            padding-top: 15px;
        }
        
        .sidebar-loja .logout-link a {
            color: var(--cor-erro) !important;
            font-weight: 600 !important;
        }
        .sidebar-loja .logout-link a:hover {
            background-color: var(--cor-erro) !important;
            color: white !important;
        }

        /* Conteúdo Principal */
        .content-loja {
            flex-grow: 1;
            padding: 30px;
        }

        .content-loja h2 {
            color: var(--cor-marrom-acao);
            margin-bottom: 20px;
            border-bottom: 1px solid var(--cor-borda);
            padding-bottom: 10px;
            text-transform: capitalize;
        }
        
        .dash-subtitle {
            font-size: 1.1em;
            color: #666;
            margin-bottom: 20px;
        }

        /* Estilos das Mensagens */
        .msg-sucesso, .msg-erro, .msg-aviso {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
            font-size: 0.95em;
        }

        .msg-sucesso {
            background-color: #d4edda;
            color: var(--cor-sucesso);
            border: 1px solid #c3e6cb;
        }

        .msg-erro {
            background-color: #f8d7da;
            color: var(--cor-erro);
            border: 1px solid #f5c6cb;
        }

        .msg-aviso {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        /* Estilo para Botões Padrão */
        .btn-cadastro-produto {
            display: inline-block;
            background-color: var(--cor-marrom-acao);
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: background-color 0.2s;
            margin-bottom: 20px;
            border: none;
            cursor: pointer;
        }
        
        .btn-cadastro-produto:hover {
            background-color: var(--cor-marrom-escuro);
        }

        /* ====================================
           ESTILOS DO DASHBOARD
           ==================================== */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .dashboard-card {
            background-color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            border-left: 5px solid var(--cor-marrom-acao);
            transition: transform 0.3s ease;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .dashboard-card h3 {
            margin-top: 0;
            color: #666;
            font-size: 1em;
            text-transform: uppercase;
        }

        .dashboard-card .data-value {
            display: block;
            font-size: 2.2em;
            font-weight: bold;
            color: var(--cor-marrom-escuro);
            margin: 5px 0 10px;
        }
        
        .dashboard-card.metric-alert {
            border-left-color: var(--cor-erro);
        }

        /* Estilos da Tabela de Pedidos/Produtos */
        .table-responsive {
            overflow-x: auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .inventory-table {
            width: 100%;
            border-collapse: collapse;
        }

        .inventory-table th, .inventory-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #EEE;
        }

        .inventory-table th {
            background-color: var(--cor-secundaria);
            color: var(--cor-marrom-escuro);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
        }

        .inventory-table td {
            font-size: 0.95em;
        }

        .inventory-table tr:hover {
            background-color: #FAF6F0;
        }
        
        /* Ações na Tabela */
        .btn-acao {
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            margin-right: 5px;
            font-size: 0.85em;
            cursor: pointer;
            border: 1px solid transparent;
            transition: opacity 0.2s;
        }
        
        .btn-acao:hover {
            opacity: 0.8;
        }

        .btn-editar {
            background-color: var(--cor-principal);
            color: var(--cor-marrom-escuro);
        }

        .btn-excluir {
            background-color: var(--cor-erro);
            color: white;
        }

        .produto-miniatura {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .estoque-baixo {
            color: var(--cor-aviso);
            font-weight: bold;
        }
        
        .estoque-zero {
            color: var(--cor-erro);
            font-weight: bold;
        }
        
        .dashboard-actions {
            margin-top: 20px;
            text-align: right;
        }
        
        .btn-acao-secundaria {
            display: inline-block;
            background-color: white;
            color: var(--cor-marrom-acao);
            padding: 8px 15px;
            text-decoration: none;
            border: 1px solid var(--cor-marrom-acao);
            border-radius: 8px;
            font-weight: bold;
            transition: background-color 0.2s, color 0.2s;
        }
        
        .btn-acao-secundaria:hover {
            background-color: var(--cor-marrom-acao);
            color: white;
        }
        
        /* ====================================
           ESTILOS DE PEDIDOS
           ==================================== */
        .pedido-card {
            background-color: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border-left: 5px solid var(--cor-marrom-acao);
            transition: transform 0.3s ease;
        }
        
        .pedido-card:hover {
            border-left-color: var(--cor-marrom-escuro);
        }

        .pedido-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px dashed var(--cor-borda);
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .pedido-header h4 {
            margin: 0;
            font-size: 1.1em;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            font-size: 0.85em;
        }

        .status-aguardando-pagamento { background-color: var(--cor-status-aguardando); }
        .status-processando { background-color: var(--cor-status-processando); }
        .status-em-entrega { background-color: var(--cor-status-entrega); }
        .status-entregue { background-color: var(--cor-status-entregue); }
        .status-cancelado { background-color: var(--cor-erro); }

        .pedido-details p {
            margin: 5px 0;
            font-size: 0.9em;
        }

        .pedido-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed var(--cor-borda);
        }

        .btn-pedido-action {
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn-pedido-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-processar {
            background-color: var(--cor-status-processando);
            color: white;
        }

        .btn-entregue {
            background-color: var(--cor-status-entregue);
            color: white;
        }

        /* Estilo para o Dropdown de Status na aba Pedidos */
        .select-status {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid var(--cor-borda);
            background-color: white;
            font-size: 1em;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        
        .select-status:hover, .select-status:focus {
            border-color: var(--cor-marrom-acao);
            outline: none;
        }
        
        /* Estilo para o Dropdown de Status no Dashboard */
        .select-status-dash {
            padding: 5px 8px;
            border-radius: 5px;
            border: 1px solid var(--cor-borda);
            background-color: white;
            font-size: 0.9em;
            cursor: pointer;
            transition: border-color 0.2s;
            min-width: 150px; 
        }

        /* NOVO: Estilo para o Botão Salvar Status (Dashboard e Pedidos) */
        .btn-salvar-status {
            background-color: #28a745; /* Verde */
            color: white;
            border: none;
            border-radius: 5px;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: bold;
            line-height: 1; 
            transition: background-color 0.2s;
            flex-shrink: 0; 
            height: 35px; 
        }

        .btn-salvar-status:hover {
            background-color: #218838;
        }

        /* Garante que os formulários de submissão automática funcionem bem dentro da tabela */
        .inventory-table td {
            vertical-align: middle;
        }
        
        /* ====================================
           ESTILOS DE CONFIGURAÇÕES
           ==================================== */
        .config-form {
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            max-width: 600px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .config-form label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--cor-marrom-escuro);
        }

        .config-form input[type="text"],
        .config-form input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--cor-borda);
            border-radius: 8px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        
        .config-form input[type="email"]:disabled {
            background-color: #f0f0f0;
            cursor: not-allowed;
        }
        
        .btn-salvar-config {
            background-color: var(--cor-marrom-acao);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-salvar-config:hover {
            background-color: var(--cor-marrom-escuro);
        }
        
        .config-info {
            background-color: #f7f7f7;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--cor-borda);
            max-width: 600px;
        }

        .config-info h4 {
            color: var(--cor-marrom-acao);
            border-bottom: 1px dashed var(--cor-borda);
            padding-bottom: 10px;
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .config-info .status-badge {
            padding: 3px 8px;
            background-color: var(--cor-sucesso);
            color: white;
            border-radius: 4px;
        }

        /* ====================================
           ANIMAÇÕES DE FADE-IN (SCROLL)
           ==================================== */
        .scroll-fade-up {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease-out, transform 0.5s ease-out;
        }

        .scroll-fade-up.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ========================================= */
/* ESTILOS MODERNOS E RESPONSIVOS PARA BOTÕES EXTRA */
/* ========================================= */

.sidebar-actions-rodape {
    /* Garante que os botões fiquem fixos na base da sidebar */
    margin-top: auto; 
    padding: 15px;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
    
    /* Configurações para Centralização e Responsividade */
    display: flex; 
    flex-direction: column;
    align-items: center; /* Centraliza os itens horizontalmente (o quebra-cabeça principal) */
    gap: 10px;
}

.btn-sidebar {
    /* Define a largura máxima para responsividade em telas grandes */
    max-width: 250px; 
    width: 100%; /* Garante que o botão ocupe 100% da max-width definida */
    padding: 10px 15px;
    border: none;
    border-radius: 8px;
    font-size: 1em;
    font-weight: 700;
    text-align: center;
    text-decoration: none;
    cursor: pointer;
    
    /* Configuração da Transição Moderna */
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    
    /* Sombra suave para efeito 'flutuante' */
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* --- Botão VOLTAR (Mantenha as cores e hover) --- */
.btn-voltar {
    background-color: var(--cor-marrom-acao);
    color: white;
}

.btn-voltar:hover {
    box-shadow: 0 8px 12px rgba(0, 0, 0, 0.2);
    transform: translateY(-2px) scale(1.02);
    background-color: #A0522D; 
}

/* --- Botão LOGOUT (Mantenha as cores e hover) --- */
.btn-logout {
    background-color: #dc3545; 
    color: white;
}

.btn-logout:hover {
    box-shadow: 0 8px 12px rgba(0, 0, 0, 0.2);
    transform: translateY(-2px) scale(1.02);
    background-color: #c82333; 
}

    </style>
</head>
<body>
    <div class="container-loja">
        
        <nav class="sidebar-loja">
            <h3>Painel da Loja</h3>
            <h4>Olá, <?php echo htmlspecialchars($nm_loja); ?>!</h4>
            
            <ul>
                <li>
                    <a href="painel_loja.php?aba=dashboard" class="<?php echo ($aba_ativa == 'dashboard') ? 'active' : ''; ?>">
                        🏠 Dashboard
                    </a>
                </li>
                <li>
                    <a href="painel_loja.php?aba=pedidos" class="<?php echo ($aba_ativa == 'pedidos') ? 'active' : ''; ?>">
                        📦 Pedidos Pendentes
                    </a>
                </li>
                <li>
                    <a href="painel_loja.php?aba=produtos" class="<?php echo ($aba_ativa == 'produtos') ? 'active' : ''; ?>">
                        🍬 Meus Produtos
                    </a>
                </li>
                <li>
                    <a href="painel_loja.php?aba=configuracoes" class="<?php echo ($aba_ativa == 'configuracoes') ? 'active' : ''; ?>">
                        ⚙️ Configurações
                    </a>
                </li>
            </ul>
            
            <hr>
            
            <div style="padding: 10px; font-size: 0.9em; color: var(--cor-marrom-escuro);">
                <p>📞 Telefone: <?php echo htmlspecialchars($dados_loja['nr_telefone'] ?? 'Não cadastrado'); ?></p>
            </div>

            <div class="sidebar-actions-rodape">
                <a href="index.php" class="btn-sidebar btn-voltar">🏠 Voltar à Tela Inicial</a>
                <a href="logout.php" class="btn-sidebar btn-logout">Logout 🚪</a>
            </div>
        </nav>            
        </nav>

        <main class="content-loja">
            <h2><?php 
                echo ucwords(str_replace('_', ' ', $aba_ativa)); 
            ?></h2>
            
            <?php
            // 4. CARREGAMENTO DO CONTEÚDO (CHAMADA DA FUNÇÃO)
            echo carregar_conteudo($aba_ativa, $mysqli, $cd_loja, $nm_loja, $dados_loja, $mensagem_config, $mensagem_sessao);
            ?>
            
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            /**
             * Lógica de animação Fade-In ao rolar a página (Intersection Observer API)
             */
            const elementsToObserve = document.querySelectorAll('.scroll-fade-up');
            
            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const target = entry.target;
                        target.classList.add('is-visible');
                        
                        // Lógica de Delay Escalonado (Para cards de métrica)
                        const delay = target.getAttribute('data-delay');
                        if (delay) {
                            // Adiciona o delay e remove o atributo para evitar re-aplicação
                            target.style.transitionDelay = delay + 's';
                            target.removeAttribute('data-delay'); 
                        }

                        // Para a observação para que a animação não se repita.
                        observer.unobserve(target);
                    }
                });
            }, {
                rootMargin: '0px 0px -100px 0px', // Aciona um pouco antes de chegar no rodapé
                threshold: 0.1 
            });

            // Inicia a observação para todos os elementos
            elementsToObserve.forEach(element => {
                observer.observe(element);
            });


            /**
             * Função JavaScript para confirmar a exclusão de um produto.
             */
            window.confirmDelete = function(idProduto) {
                if (confirm("Tem certeza que deseja EXCLUIR permanentemente este produto? Esta ação não pode ser desfeita.")) {
                    // O arquivo delete_produto.php precisa ser criado com a lógica de exclusão
                    window.location.href = 'delete_produto.php?id=' + idProduto;
                }
            }
        });
    </script>

    <?php
    // Fechamento da conexão
    $mysqli->close(); 
    ?>
</body>
</html>