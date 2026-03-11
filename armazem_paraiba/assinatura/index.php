<?php
include_once "../conecta.php";
include_once "componentes/layout_header.php";
?>
<style>
    /* Estilos Gerais do Módulo */
    .assinatura-dashboard {
        font-family: 'Open Sans', sans-serif;
        padding: 20px 0;
    }

    /* Cabeçalho do Módulo - Compacto e Elegante */
    .modulo-header {
        background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
        color: white;
        padding: 20px 30px;
        border-radius: 8px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .header-content h2 {
        margin: 0 0 5px 0;
        font-size: 24px;
        font-weight: 700;
        color: #fff;
    }

    .header-content p {
        margin: 0;
        font-size: 14px;
        opacity: 0.9;
        max-width: 600px;
    }

    .header-icon {
        font-size: 40px;
        opacity: 0.8;
    }

    /* Grid de Cards */
    .cards-grid {
        display: flex;
        flex-wrap: wrap;
        margin: -10px;
    }

    .card-wrapper {
        padding: 10px;
        width: 100%;
    }
    
    @media (min-width: 576px) { .card-wrapper { width: 50%; } }
    @media (min-width: 992px) { .card-wrapper { width: 25%; } } /* 4 colunas em telas grandes */

    /* Design do Card */
    .card-modulo {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        height: 100%;
        position: relative;
        overflow: hidden;
        border: 1px solid #e0e0e0;
        display: flex;
        flex-direction: column;
        text-decoration: none !important; /* Remove sublinhado do link */
    }

    .card-modulo:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-color: transparent;
    }

    /* Faixa colorida lateral ou superior */
    .card-border-top {
        height: 4px;
        width: 100%;
        position: absolute;
        top: 0;
        left: 0;
    }

    .card-body-content {
        padding: 25px 20px;
        text-align: center;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    /* Ícone com círculo de fundo */
    .icon-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 15px;
        font-size: 24px;
        transition: transform 0.3s;
    }

    .card-modulo:hover .icon-circle {
        transform: scale(1.1);
    }

    .card-title {
        font-size: 16px;
        font-weight: 700;
        color: #333;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .card-desc {
        font-size: 12px;
        color: #777;
        line-height: 1.4;
    }

    /* Cores Específicas - Padronizado com o Header */
    .card-border-top { background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%); }
    .icon-circle { 
        background: rgba(75, 108, 183, 0.1); 
        color: #4b6cb7; /* Cor base do gradiente */
    }

    /* Hover effect: Gradient icon background */
    .card-modulo:hover .icon-circle {
        background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
        color: #fff;
    }

    /* Estilos específicos removidos em favor do padrão único */
    /* .style-blue, .style-green, etc... removidos */

</style>

<div class="assinatura-dashboard">

    <!-- Cabeçalho -->
    <div class="modulo-header">
        <div class="header-content">
            <h2>Módulo de Assinatura Digital</h2>
            <p>
                Gerencie todo o ciclo de vida dos documentos: envio individual, em lote, 
                assinatura eletrônica e finalização com carimbo do tempo ICP-Brasil.
            </p>
        </div>
        <div class="header-icon">
            <i class="fa fa-file-text-o"></i>
        </div>
    </div>

    <!-- Grid de Opções -->
    <div class="cards-grid">
        
        <!-- Nova Assinatura -->
        <div class="card-wrapper">
            <a href="<?php echo $baseAssinatura; ?>/nova_assinatura.php" class="card-modulo">
                <div class="card-border-top"></div>
                <div class="card-body-content">
                    <div class="icon-circle">
                        <i class="fa fa-pencil"></i>
                    </div>
                    <div class="card-title">Nova Assinatura</div>
                    <div class="card-desc">Envio de arquivo único para assinatura de um signatário.</div>
                </div>
            </a>
        </div>

        <!-- Envio Múltiplo -->
        <div class="card-wrapper">
            <a href="<?php echo $baseAssinatura; ?>/enviar_documento.php" class="card-modulo">
                <div class="card-border-top"></div>
                <div class="card-body-content">
                    <div class="icon-circle">
                        <i class="fa fa-users"></i>
                    </div>
                    <div class="card-title">Envio em Massa</div>
                    <div class="card-desc">Envie um documento para múltiplos signatários de uma vez.</div>
                </div>
            </a>
        </div>

        <!-- Finalizar ICP-Brasil -->
        <div class="card-wrapper">
            <a href="<?php echo $baseAssinatura; ?>/finalizar.php" class="card-modulo">
                <div class="card-border-top"></div>
                <div class="card-body-content">
                    <div class="icon-circle">
                        <i class="fa fa-certificate"></i>
                    </div>
                    <div class="card-title">Finalizar (ICP)</div>
                    <div class="card-desc">Aplique o carimbo do tempo e valide juridicamente.</div>
                </div>
            </a>
        </div>

        <!-- Consultar -->
        <div class="card-wrapper">
            <a href="<?php echo $baseAssinatura; ?>/consultar.php" class="card-modulo">
                <div class="card-border-top"></div>
                <div class="card-body-content">
                    <div class="icon-circle">
                        <i class="fa fa-search"></i>
                    </div>
                    <div class="card-title">Consultar</div>
                    <div class="card-desc">Histórico e status de documentos enviados.</div>
                </div>
            </a>
        </div>

        <!-- Relatórios -->
        <div class="card-wrapper">
            <a href="#" class="card-modulo">
                <div class="card-border-top"></div>
                <div class="card-body-content">
                    <div class="icon-circle">
                        <i class="fa fa-pie-chart"></i>
                    </div>
                    <div class="card-title">Relatórios</div>
                    <div class="card-desc">Métricas de desempenho e assinaturas pendentes. (Em Breve)</div>
                </div>
            </a>
        </div>

        <!-- Configurações -->
        <div class="card-wrapper">
            <a href="#" class="card-modulo">
                <div class="card-border-top"></div>
                <div class="card-body-content">
                    <div class="icon-circle">
                        <i class="fa fa-cog"></i>
                    </div>
                    <div class="card-title">Configurações</div>
                    <div class="card-desc">Gerenciar certificados e permissões de acesso. (Em Breve)</div>
                </div>
            </a>
        </div>

    </div>

</div>

<?php
include_once "componentes/layout_footer.php";
?>
