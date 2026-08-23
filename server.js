require("dotenv").config();
const express = require("express");
const bodyParser = require("body-parser");
const mysql = require("mysql");
const cors = require("cors");
const morgan = require("morgan");
const http = require("http");

const app = express();
const port = process.env.PORT || 5100;

// Middlewares
app.use(cors({
  origin: '*',
  methods: ['GET', 'POST'],
  allowedHeaders: ['Content-Type', 'Authorization']
}));
app.use(morgan("dev")); // Logging
app.use(bodyParser.urlencoded({ extended: true }));
app.use(bodyParser.json());
app.use(express.static("public"));

// Conexão com o banco
const db = mysql.createConnection({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_DATABASE,
});

db.connect((err) => {
    if (err) {
        console.error("Erro ao conectar no banco:", err);
        process.exit(1); // encerra a aplicação
    }
    console.log("Conectado ao banco de dados.");
});

app.use(bodyParser.urlencoded({ extended: true }));
app.use(bodyParser.json());
app.use(express.static("public"));









/*
==================================================
DASHBOARD - RESUMO GERAL
==================================================
*/
app.get("/dashboard/resumo", (req, res) => {

    const query = `
        SELECT
            COUNT(*) AS totalPosicoes,
            COUNT(DISTINCT vehicle_plate) AS totalPlacas,
            COUNT(DISTINCT cliente) AS totalClientes,
            COUNT(DISTINCT empresaApi) AS totalEmpresas
        FROM TECHPS_LOGISTICA_POS
        WHERE moduleTime >= NOW() - INTERVAL 24 HOUR
    `;

    db.query(query, (err, results) => {

        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }

        res.json(results[0]);
    });

});


/*
==================================================
EMPRESAS
==================================================
*/
app.get("/dashboard/empresas", (req, res) => {

    const query = `
        SELECT
            empresaApi,
            COUNT(*) AS totalPosicoes,
            COUNT(DISTINCT vehicle_plate) AS totalPlacas,
            MAX(moduleTime) AS ultimaPosicao
        FROM TECHPS_LOGISTICA_POS
        WHERE moduleTime >= NOW() - INTERVAL 24 HOUR
        GROUP BY empresaApi
        ORDER BY totalPosicoes DESC
    `;

    db.query(query, (err, results) => {

        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }

        res.json(results);
    });

});


/*
==================================================
CLIENTES
==================================================
*/
app.get("/dashboard/clientes", (req, res) => {

    const query = `
        SELECT
            cliente,
            COUNT(*) AS totalPosicoes,
            COUNT(DISTINCT vehicle_plate) AS totalPlacas,
            MAX(moduleTime) AS ultimaPosicao
        FROM TECHPS_LOGISTICA_POS
        WHERE moduleTime >= NOW() - INTERVAL 24 HOUR
        GROUP BY cliente
        ORDER BY totalPosicoes DESC
    `;

    db.query(query, (err, results) => {

        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }

        res.json(results);
    });

});


/*
==================================================
PLACAS DE UM CLIENTE
==================================================
*/
app.get("/dashboard/cliente/:cliente", (req, res) => {

    const cliente = req.params.cliente;

    const query = `
        SELECT
            vehicle_plate,
            COUNT(*) AS totalPosicoes,
            MAX(moduleTime) AS ultimaPosicao
        FROM TECHPS_LOGISTICA_POS
        WHERE cliente = ?
        AND moduleTime >= NOW() - INTERVAL 24 HOUR
        GROUP BY vehicle_plate
        ORDER BY totalPosicoes DESC
    `;

    db.query(query, [cliente], (err, results) => {

        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }

        res.json(results);
    });

});


/*
==================================================
DETALHE DE UMA PLACA
==================================================
*/
app.get("/dashboard/placa/:placa", (req, res) => {

    const placa = req.params.placa;

    const query = `
        SELECT
            vehicle_plate,
            cliente,
            empresaApi,
            COUNT(*) AS totalPosicoes,
            MIN(moduleTime) AS primeiraPosicao,
            MAX(moduleTime) AS ultimaPosicao
        FROM TECHPS_LOGISTICA_POS
        WHERE vehicle_plate = ?
        GROUP BY
            vehicle_plate,
            cliente,
            empresaApi
    `;

    db.query(query, [placa], (err, results) => {

        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }

        res.json(results[0] || {});
    });

});


/*
==================================================
PLACAS OFFLINE > 24 HORAS
==================================================
*/
app.get("/dashboard/placas-offline", (req, res) => {

    const query = `
        SELECT
            cliente,
            empresaApi,
            vehicle_plate,
            MAX(moduleTime) AS ultimaPosicao,
            TIMESTAMPDIFF(
                HOUR,
                MAX(moduleTime),
                NOW()
            ) AS horasSemComunicacao
        FROM TECHPS_LOGISTICA_POS
        GROUP BY
            cliente,
            empresaApi,
            vehicle_plate
        HAVING MAX(moduleTime) < NOW() - INTERVAL 24 HOUR
        ORDER BY ultimaPosicao ASC
    `;

    db.query(query, (err, results) => {

        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }

        res.json({
            total: results.length,
            placas: results
        });
    });

});


/*
==================================================
STATUS DAS PLACAS
==================================================
*/
app.get("/dashboard/status", (req, res) => {

    const query = `
        SELECT
            cliente,
            empresaApi,
            vehicle_plate,
            MAX(moduleTime) AS ultimaPosicao,
            TIMESTAMPDIFF(
                MINUTE,
                MAX(moduleTime),
                NOW()
            ) AS minutosSemComunicacao
        FROM TECHPS_LOGISTICA_POS
        GROUP BY
            cliente,
            empresaApi,
            vehicle_plate
    `;

    db.query(query, (err, results) => {

        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }

        const retorno = results.map(item => {

            let status = "ONLINE";

            if (item.minutosSemComunicacao > 1440) {
                status = "OFFLINE";
            } else if (item.minutosSemComunicacao > 360) {
                status = "ATENCAO";
            }

            return {
                ...item,
                status
            };
        });

        res.json(retorno);

    });

});


/*
==================================================
POSICOES POR HORA
==================================================
*/
app.get("/dashboard/posicoes-hora", (req, res) => {

    const query = `
        SELECT
            DATE_FORMAT(
                moduleTime,
                '%Y-%m-%d %H:00:00'
            ) AS hora,
            COUNT(*) AS total
        FROM TECHPS_LOGISTICA_POS
        WHERE moduleTime >= NOW() - INTERVAL 24 HOUR
        GROUP BY hora
        ORDER BY hora
    `;

    db.query(query, (err, results) => {

        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }

        res.json(results);
    });

});


/*
==================================================
TOP PLACAS
==================================================
*/
app.get("/dashboard/top-placas", (req, res) => {

    const limit = parseInt(req.query.limit || 20);

    const query = `
        SELECT
            vehicle_plate,
            cliente,
            COUNT(*) AS totalPosicoes,
            MAX(moduleTime) AS ultimaPosicao
        FROM TECHPS_LOGISTICA_POS
        WHERE moduleTime >= NOW() - INTERVAL 24 HOUR
        GROUP BY
            vehicle_plate,
            cliente
        ORDER BY totalPosicoes DESC
        LIMIT ?
    `;

    db.query(query, [limit], (err, results) => {

        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }

        res.json(results);
    });

});















































app.get("/empresas-posicoes-hoje", (req, res) => {

    const query = `
        SELECT 
            t.empresaApi,
            t.cnpj,
            t.vehicle_plate,
            t.moduleTime AS ultima_posicao
        FROM TECHPS_LOGISTICA_POS t
        INNER JOIN (
            SELECT 
                vehicle_plate,
                MAX(moduleTime) AS ultima_posicao
            FROM TECHPS_LOGISTICA_POS
            WHERE empresaApi IS NOT NULL
            AND empresaApi <> ''
            GROUP BY vehicle_plate
        ) ult
        ON t.vehicle_plate = ult.vehicle_plate
        AND t.moduleTime = ult.ultima_posicao
        ORDER BY t.empresaApi ASC, t.moduleTime DESC;
    `;

    db.query(query, (err, results) => {

        if (err) {

            console.error("Erro ao buscar posições:", err);

            return res.status(500).json({
                error: true,
                message: "Erro ao buscar posições"
            });
        }

        const empresas = {};

        results.forEach(row => {

            const empresa = row.empresaApi || "SEM_EMPRESA";

            if (!empresas[empresa]) {

                empresas[empresa] = {
                    empresaApi: empresa,
                    cnpj: row.cnpj,
                    totalVeiculos: 0,
                    veiculos: []
                };
            }

            empresas[empresa].veiculos.push({
                placa: row.vehicle_plate,
                ultimaPosicao: row.ultima_posicao
            });

            empresas[empresa].totalVeiculos++;
        });

        res.json({
            totalEmpresas: Object.keys(empresas).length,
            empresas: Object.values(empresas)
        });
    });
});

app.get("/plates", (req, res) => {
    const cnpjList = req.query.cnpj; // Obtém o parâmetro de consulta, esperado como uma string de CNPJs separados por vírgula

    // Verifica se o CNPJ foi fornecido
    if (!cnpjList) {
        return res.status(400).send("CNPJ is required.");
    }

    // Divide a string de CNPJs em um array e faz o tratamento para evitar SQL Injection
    const cnpjArray = cnpjList.split(',').map(cnpj => cnpj.trim());

    // Monta a consulta com placeholders para evitar SQL Injection
    const placeholders = cnpjArray.map(() => '?').join(', '); // Cria um string de placeholders
    const query = `SELECT DISTINCT vehicle_plate FROM TECHPS_LOGISTICA_POS WHERE cnpj IN (${placeholders})`;

    // Consulta ao banco de dados para obter as placas com base nos CNPJs
    db.query(query, cnpjArray, (err, results) => {
        if (err) {
            console.error("Erro ao buscar placas:", err);
            res.status(500).send("Internal Server Error");
            return;
        }

        const plates = results.map((row) => row.vehicle_plate); // Corrigido o nome do campo
        res.json(plates);
    });
});





app.post("/data", (req, res) => {
    const { plate, date, speed } = req.body;

    console.log("Received data:", { plate, date, speed });

    const query = `
        SELECT *
        FROM TECHPS_LOGISTICA_POS
        WHERE vehicle_plate = ? AND DATE(moduleTime) = ? AND speed <= ?
        ORDER BY moduleTime ASC
        LIMIT 1000000000;
    `;

    db.query(query, [plate, date, speed], (err, results) => {
        if (err) {
            console.error("Error fetching data:", err);
            res.status(500).send("Error fetching data");
            return;
        }
        console.log("SQL query executed successfully.");
        res.json(results);
    });
});

app.post("/data1",cors(), (req, res) => {
    const { plate, date_start, date_end, speed } = req.body;

    console.log("Received data:", { plate, date_start, date_end, speed });

    if (!date_start || !date_end) {
        return res.status(400).send("As datas de início e fim são obrigatórias.");
    }

    const query = `
        SELECT *
        FROM TECHPS_LOGISTICA_POS
        WHERE vehicle_plate = ?
        AND STR_TO_DATE(
            IF(LOCATE('T', moduleTime) > 0, REPLACE(moduleTime, 'T', ' '), moduleTime),
            '%Y-%m-%d %H:%i:%s'
        ) >= STR_TO_DATE(?, '%Y-%m-%d %H:%i:%s')
        AND STR_TO_DATE(
            IF(LOCATE('T', moduleTime) > 0, REPLACE(moduleTime, 'T', ' '), moduleTime),
            '%Y-%m-%d %H:%i:%s'
        ) <= STR_TO_DATE(?, '%Y-%m-%d %H:%i:%s')
        AND speed <= ?
        ORDER BY moduleTime ASC
        LIMIT 1000000000;
    `;

    db.query(query, [plate, date_start, date_end, speed], (err, results) => {
        if (err) {
            console.error("Error fetching data:", err);
            return res.status(500).send("Error fetching data");
        }

        console.log("SQL query executed successfully.");
        res.json(results);
    });
});


app.get("/health", (req, res) => {
  res.status(200).send("OK");
});




app.get("/export/top", (req, res) => {
  const top = parseInt(req.query.top) || 1;
  const offset = parseInt(req.query.offset) || 0; // novo

  const query = `
    SELECT *
    FROM TECHPS_LOGISTICA_POS
    LIMIT ? OFFSET ?
  `;

  db.query(query, [top, offset], (err, results) => {
    if (err) {
      console.error("Erro ao buscar top registros:", err);
      return res.status(500).json({ erro: err.message });
    }

    res.json({
      top,
      offset,
      rows: results
    });
  });
});



app.post("/insertData", (req, res) => {
    const {
        vehicle_plate,
        longitude,
        latitude,
        speed,
        ignition,
        moduleTime,
        hodometro,
        endereco,
        cnpj,
        cliente,
        empresaApi
    } = req.body;

    // Validação básica
    if (!vehicle_plate || !moduleTime || !cnpj) {
        return res.status(400).json({ error: "vehicle_plate, moduleTime e cnpj são obrigatórios." });
    }

    const query = `
        INSERT INTO TECHPS_LOGISTICA_POS
        (vehicle_plate, longitude, latitude, speed, ignition, moduleTime, hodometro, endereco, cnpj, cliente, empresaApi)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `;

const ignitionValue = ignition === true ? 'true' : 'false';

const values = [
    vehicle_plate,
    longitude || null,
    latitude || null,
    speed || 0,
    ignitionValue,
    moduleTime,
    hodometro || null,
    endereco || null,
    cnpj,
    cliente || null,
    empresaApi || null
];

    db.query(query, values, (err, result) => {
        if (err) {
            console.error("Erro ao inserir dados:", err);
            return res.status(500).json({ error: "Erro ao inserir dados no banco." });
        }

        console.log("Dados inseridos com sucesso:", result.insertId);
        res.json({ success: true, id: result.insertId });
    });
});



/*
==================================================
LOG DE EXECUÇÕES (TECHPS_LOGISTICA_LOG)
==================================================
*/

// Resumo geral das execuções
app.get("/dashboard/log/resumo", (req, res) => {
    const query = `
        SELECT
            COUNT(*) AS total_execucoes,
            COUNT(DISTINCT empresa_api) AS total_integracoes,
            SUM(quantidade_posicoes) AS total_posicoes,
            SUM(CASE WHEN quantidade_posicoes = 0 THEN 1 ELSE 0 END) AS execucoes_vazias,
            MAX(data_hora) AS ultima_execucao,
            MIN(data_hora) AS primeira_execucao
        FROM TECHPS_LOGISTICA_LOG
        WHERE data_hora >= NOW() - INTERVAL 24 HOUR
    `;

    db.query(query, (err, results) => {
        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }
        res.json(results[0]);
    });
});

// Status de cada integração
app.get("/dashboard/log/integracoes", (req, res) => {
    const query = `
        SELECT
            empresa_api,
            COUNT(*) AS total_execucoes,
            SUM(quantidade_posicoes) AS total_posicoes,
            SUM(CASE WHEN quantidade_posicoes = 0 THEN 1 ELSE 0 END) AS execucoes_vazias,
            MAX(data_hora) AS ultima_execucao,
            MIN(data_hora) AS primeira_execucao,
            MAX(ciclo) AS ultimo_ciclo,
            TIMESTAMPDIFF(MINUTE, MAX(data_hora), NOW()) AS minutos_sem_execucao,
            CASE
                WHEN MAX(data_hora) >= NOW() - INTERVAL 15 MINUTE THEN 'OK'
                WHEN MAX(data_hora) >= NOW() - INTERVAL 60 MINUTE THEN 'ATENCAO'
                ELSE 'CRITICO'
            END AS status
        FROM TECHPS_LOGISTICA_LOG
        WHERE data_hora >= NOW() - INTERVAL 24 HOUR
        GROUP BY empresa_api
        ORDER BY status ASC, ultima_execucao DESC
    `;

    db.query(query, (err, results) => {
        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }
        res.json(results);
    });
});

// Últimas execuções (geral)
app.get("/dashboard/log/ultimas", (req, res) => {
    const limit = parseInt(req.query.limit || 50);

    const query = `
        SELECT
            data_hora,
            empresa_api,
            cliente,
            cnpj,
            quantidade_posicoes,
            ciclo
        FROM TECHPS_LOGISTICA_LOG
        ORDER BY data_hora DESC
        LIMIT ?
    `;

    db.query(query, [limit], (err, results) => {
        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }
        res.json(results);
    });
});

// Posições por hora (log)
app.get("/dashboard/log/posicoes-hora", (req, res) => {
    const query = `
        SELECT
            DATE_FORMAT(data_hora, '%Y-%m-%d %H:00:00') AS hora,
            empresa_api,
            SUM(quantidade_posicoes) AS total_posicoes
        FROM TECHPS_LOGISTICA_LOG
        WHERE data_hora >= NOW() - INTERVAL 24 HOUR
        GROUP BY hora, empresa_api
        ORDER BY hora
    `;

    db.query(query, (err, results) => {
        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }
        res.json(results);
    });
});

// Posições por cliente (log)
app.get("/dashboard/log/clientes", (req, res) => {
    const query = `
        SELECT
            cliente,
            cnpj,
            empresa_api,
            SUM(quantidade_posicoes) AS total_posicoes,
            COUNT(*) AS total_execucoes,
            MAX(data_hora) AS ultima_execucao
        FROM TECHPS_LOGISTICA_LOG
        WHERE data_hora >= NOW() - INTERVAL 24 HOUR
            AND cliente <> ''
        GROUP BY cliente, cnpj, empresa_api
        ORDER BY total_posicoes DESC
    `;

    db.query(query, (err, results) => {
        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }
        res.json(results);
    });
});

// Saúde geral (todas as integrações estão rodando?)
app.get("/dashboard/log/saude", (req, res) => {
    const query = `
        SELECT
            empresa_api,
            MAX(data_hora) AS ultima_execucao,
            TIMESTAMPDIFF(MINUTE, MAX(data_hora), NOW()) AS minutos_sem_execucao,
            SUM(quantidade_posicoes) AS total_posicoes_24h,
            CASE
                WHEN MAX(data_hora) >= NOW() - INTERVAL 15 MINUTE THEN 'OK'
                WHEN MAX(data_hora) >= NOW() - INTERVAL 60 MINUTE THEN 'ATENCAO'
                ELSE 'CRITICO'
            END AS status
        FROM TECHPS_LOGISTICA_LOG
        WHERE data_hora >= NOW() - INTERVAL 24 HOUR
        GROUP BY empresa_api
    `;

    db.query(query, (err, results) => {
        if (err) {
            console.error(err);
            return res.status(500).json(err);
        }

        const total = results.length;
        const ok = results.filter(r => r.status === 'OK').length;
        const atencao = results.filter(r => r.status === 'ATENCAO').length;
        const critico = results.filter(r => r.status === 'CRITICO').length;

        res.json({
            total_integracoes: total,
            integracoes_ok: ok,
            integracoes_atencao: atencao,
            integracoes_critico: critico,
            saudavel: critico === 0,
            detalhes: results
        });
    });
});

/* ==================================================
SUPORTE - CHAMADOS TÉCNICOS (envio para API externa)
As rotas recebem os dados do chamado e gravam no banco
externo de suporte (SUPORTE_DB_* no .env).
Rotas: POST/GET /suporte/*
================================================== */
const multer = require("multer");
const crypto = require("crypto");
const fs = require("fs");
const path = require("path");
const nodemailer = require("nodemailer");

// Status permitidos do chamado (fluxo de atendimento).
const SUPORTE_STATUS = {
    aberto:               "Aberto",
    em_analise:           "Em Análise",
    em_andamento:         "Em Andamento",
    aguardando_cliente:   "Aguardando retorno do cliente",
    resolvido:            "Concluído",
    cancelado:            "Cancelado",
    reaberto:             "Reaberto",
    encaminhado_ssi:      "Encaminhado a SSI",
    teste_interno:        "Teste Interno"
};

const SUPORTE_TIPOS = {
    duvida:   "Dúvida operacional",
    sugestao: "Sugestão",
    bug:      "Bug de sistema"
};

// Prioridade geral do chamado — independente do tipo e do status, vale o fluxo inteiro
// (não confundir com ssi_prioridade, que só existe pra chamados encaminhados à SSI).
const SUPORTE_PRIORIDADES = {
    baixa:   "Baixa",
    media:   "Média",
    alta:    "Alta",
    urgente: "Urgente"
};

// Envia e-mail transacional do chamado (Titan/outro SMTP). Nunca derruba a requisição.
function enviarEmailSuporte(para, assunto, html) {
    return new Promise((resolve) => {
        try {
            const host = process.env.SUPORTE_MAIL_HOST || "";
            const user = process.env.SUPORTE_MAIL_USER || "";
            const pass = process.env.SUPORTE_MAIL_PASSWORD || "";
            const from = process.env.SUPORTE_MAIL_FROM || user;
            const fromName = process.env.SUPORTE_MAIL_FROM_NAME || "Tech PS Suporte";
            if (!host || !user || !pass || !para) {
                console.warn("[SUPORTE] E-mail não enviado: SMTP ou destinatário ausentes.");
                return resolve(false);
            }
            const transporter = nodemailer.createTransport({
                host: host,
                port: parseInt(process.env.SUPORTE_MAIL_PORT || "465", 10),
                secure: parseInt(process.env.SUPORTE_MAIL_PORT || "465", 10) === 465,
                auth: { user: user, pass: pass }
            });
            transporter.sendMail({
                from: '"' + fromName + '" <' + from + '>',
                to: para,
                subject: assunto,
                html: html
            }).then(() => {
                console.log("[SUPORTE] E-mail enviado para " + para);
                resolve(true);
            }).catch((err) => {
                console.error("[SUPORTE] Erro ao enviar e-mail:", err.message);
                resolve(false);
            });
        } catch (err) {
            console.error("[SUPORTE] Erro no envio de e-mail:", err.message);
            resolve(false);
        }
    });
}

// Escape de conteúdo do usuário em HTML de e-mail (anti-XSS).
function escH(s) {
    return String(s || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

// Corpo padrão do e-mail de chamado.
function htmlEmailSuporte(ticket, titulo, avisos) {
    const statusLabel = SUPORTE_STATUS[ticket.status] || ticket.status;
    const tipoLabel = SUPORTE_TIPOS[ticket.tipo] || "";
    const ss = ticket.ssi_codigo ? ("<br>SSI: <strong>" + escH(ticket.ssi_codigo) + "</strong> (" + (ticket.ssi_prioridade === "urgente" ? "Prioritária — urgente em produção" : "Próxima atualização") + ")") : "";
    return (
        "<div style='font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;'>" +
        "<h2 style='color:#337ab7;margin-bottom:4px;'>" + escH(titulo) + "</h2>" +
        "<p style='color:#888;margin-top:0;font-size:13px;'>Chamado #" + escH(ticket.id) + " — " + escH(ticket.empresa_nome || ticket.empresa_key || "") + "</p>" +
        "<table style='border-collapse:collapse;width:100%;font-size:14px;'>" +
        "<tr><td style='padding:6px 0;color:#555;width:130px;'><strong>Status:</strong></td><td>" + escH(statusLabel) + "</td></tr>" +
        (tipoLabel ? "<tr><td style='padding:6px 0;color:#555;'><strong>Tipo:</strong></td><td>" + escH(tipoLabel) + "</td></tr>" : "") +
        (ticket.atendente_nome ? "<tr><td style='padding:6px 0;color:#555;'><strong>Atendente:</strong></td><td>" + escH(ticket.atendente_nome) + "</td></tr>" : "") +
        ss +
        "</table>" +
        "<div style='background:#f7f7f7;border:1px solid #eee;border-radius:6px;padding:12px;margin-top:12px;'>" +
        "<strong style='color:#555;'>Descrição do problema:</strong><br>" +
        "<span style='white-space:pre-wrap;color:#333;'>" + escH(ticket.descricao || "") + "</span>" +
        "</div>" +
        (avisos ? "<p style='color:#555;font-size:14px;margin-top:14px;'>" + avisos + "</p>" : "") +
        "<p style='color:#aaa;font-size:12px;margin-top:20px;'>Tech PS — Sistema de Suporte</p>" +
        "</div>"
    );
}

// E-mail interno (equipe TechPS) avisando que um chamado novo chegou.
function htmlEmailNotificacaoInterna(ticket) {
    return (
        "<div style='font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;'>" +
        "<h2 style='color:#e67e22;margin-bottom:4px;'>Novo chamado de suporte</h2>" +
        "<p style='color:#888;margin-top:0;font-size:13px;'>Chamado #" + escH(ticket.id) + " — recém aberto, aguardando análise</p>" +
        "<table style='border-collapse:collapse;width:100%;font-size:14px;'>" +
        "<tr><td style='padding:6px 0;color:#555;width:130px;'><strong>Empresa:</strong></td><td>" + escH(ticket.empresa_nome || ticket.empresa_key || "") + " (" + escH(ticket.empresa_key || "") + ")</td></tr>" +
        (ticket.setor_nome ? "<tr><td style='padding:6px 0;color:#555;'><strong>Setor:</strong></td><td>" + escH(ticket.setor_nome) + "</td></tr>" : "") +
        "<tr><td style='padding:6px 0;color:#555;'><strong>Usuário:</strong></td><td>" + escH(ticket.user_nome || ticket.user_login || "") + "</td></tr>" +
        "</table>" +
        "<div style='background:#f7f7f7;border:1px solid #eee;border-radius:6px;padding:12px;margin-top:12px;'>" +
        "<strong style='color:#555;'>Descrição do problema:</strong><br>" +
        "<span style='white-space:pre-wrap;color:#333;'>" + escH(ticket.descricao || "") + "</span>" +
        "</div>" +
        "<p style='color:#555;font-size:14px;margin-top:14px;'>Acesse a Gestão de Suporte no sistema para analisar e responder o chamado #" + escH(ticket.id) + ".</p>" +
        "<p style='color:#aaa;font-size:12px;margin-top:20px;'>Tech PS — Sistema de Suporte</p>" +
        "</div>"
    );
}

// Lê uma chave de configuração do suporte (tabela suporte_config). Retorna "" se ausente/erro.
async function obterConfigSuporte(chave) {
    try {
        const linhas = await suporteQuery("SELECT valor FROM suporte_config WHERE chave = ?", [chave]);
        return linhas.length ? String(linhas[0].valor || "") : "";
    } catch (err) {
        console.error("[SUPORTE] Erro ao ler config '" + chave + "':", err.message);
        return "";
    }
}

const SUPORTE = {
    apiKey: process.env.SUPORTE_API_KEY || "",
    adminKey: process.env.SUPORTE_ADMIN_KEY || "",
    db: null,
    maxArquivos: 6,
    maxVideos: 1,
    maxAudios: 2,
    maxBytesPadrao: 5 * 1024 * 1024,
    maxBytesVideo: 25 * 1024 * 1024,
    maxBytesAudio: 8 * 1024 * 1024,
    minDescricao: 5,
    maxDescricao: 2000,
    maxUrl: 500,
    rateLimitDia: 20,
    ttl: 300,
    mimeExt: {
        "image/jpeg": "jpg",
        "image/png": "png",
        "image/webp": "webp",
        "image/gif": "gif",
        "video/mp4": "mp4",
        "video/quicktime": "mov",
        "video/webm": "webm",
        "application/pdf": "pdf",
        "application/msword": "doc",
        "application/vnd.ms-excel": "xls",
        "application/vnd.ms-powerpoint": "ppt",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document": "docx",
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": "xlsx",
        "application/vnd.openxmlformats-officedocument.presentationml.presentation": "pptx",
        "text/plain": "txt",
        "text/csv": "csv",
        "audio/mpeg": "mp3",
        "audio/wav": "wav",
        "audio/ogg": "oga",
        "audio/mp4": "m4a",
        "audio/webm": "weba"
    },
    extImagem: ["jpg", "jpeg", "png", "webp", "gif"],
    extVideo: ["mp4", "mov", "webm"],
    extDocumento: ["pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "txt", "csv"],
    // Extensões próprias (não colidem com extVideo) — cobre upload de arquivo e gravação pelo microfone.
    extAudio: ["mp3", "wav", "oga", "ogg", "m4a", "weba"],
    uploadDir: process.env.SUPORTE_UPLOAD_DIR || path.join(__dirname, "uploads", "suporte")
};

// Conexão separada com o banco externo de suporte (não usa o db de logística).
function conectarSuporte() {
    if (SUPORTE.db) return;
    const host = process.env.SUPORTE_DB_HOST;
    const user = process.env.SUPORTE_DB_USER;
    const database = process.env.SUPORTE_DB_NAME;
    if (!host || !user || !database) {
        console.error("[SUPORTE] Banco externo não configurado no .env (SUPORTE_DB_*).");
        return;
    }
    SUPORTE.db = mysql.createConnection({
        host: host,
        user: user,
        password: process.env.SUPORTE_DB_PASSWORD,
        database: database
    });
    SUPORTE.db.on("error", (err) => {
        console.error("[SUPORTE] Erro na conexão do banco:", err.message);
        SUPORTE.db = null;
    });
    SUPORTE.db.connect((err) => {
        if (err) {
            console.error("[SUPORTE] Erro ao conectar no banco externo:", err.message);
            SUPORTE.db = null;
            return;
        }
        console.log("[SUPORTE] Conectado ao banco externo de suporte.");
    });
}

// Cria as tabelas de suporte automaticamente (seguro de rodar repetido).
function criarTabelasSuporte() {
    const sqls = [
        `CREATE TABLE IF NOT EXISTS suporte_ticket (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            empresa_key VARCHAR(60) NOT NULL,
            empresa_nome VARCHAR(150) NOT NULL DEFAULT '',
            user_id VARCHAR(50) NOT NULL DEFAULT '',
            user_login VARCHAR(100) NOT NULL DEFAULT '',
            user_nome VARCHAR(150) NOT NULL DEFAULT '',
            user_email VARCHAR(190) NOT NULL DEFAULT '',
            pagina_url VARCHAR(500) NOT NULL DEFAULT '',
            descricao TEXT NOT NULL,
            status ENUM('aberto','em_analise','em_andamento','aguardando_cliente','resolvido','cancelado','reaberto','encaminhado_ssi','teste_interno') NOT NULL DEFAULT 'aberto',
            tipo ENUM('duvida','sugestao','bug') DEFAULT NULL,
            prioridade ENUM('baixa','media','alta','urgente') NOT NULL DEFAULT 'media',
            ssi_codigo VARCHAR(30) DEFAULT NULL,
            ssi_prioridade ENUM('urgente','proxima_atualizacao') DEFAULT NULL,
            atendente_nome VARCHAR(150) DEFAULT NULL,
            aceito_em DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_ticket_empresa_data (empresa_key, created_at),
            KEY idx_ticket_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`,
        `CREATE TABLE IF NOT EXISTS suporte_arquivo (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id BIGINT UNSIGNED NOT NULL,
            nome_original VARCHAR(255) NOT NULL DEFAULT '',
            nome_gerado VARCHAR(255) NOT NULL DEFAULT '',
            mime VARCHAR(100) NOT NULL DEFAULT '',
            tamanho_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            caminho VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_arquivo_ticket (ticket_id),
            CONSTRAINT fk_arquivo_ticket FOREIGN KEY (ticket_id)
                REFERENCES suporte_ticket (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`,
        `CREATE TABLE IF NOT EXISTS suporte_rate (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            empresa_key VARCHAR(60) NOT NULL,
            user_id VARCHAR(50) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_rate_empresa_user (empresa_key, user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`,
        `CREATE TABLE IF NOT EXISTS suporte_comentario (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id BIGINT UNSIGNED NOT NULL,
            autor VARCHAR(150) NOT NULL DEFAULT '',
            autor_login VARCHAR(100) NOT NULL DEFAULT '',
            autor_tipo ENUM('gestor','empresa') NOT NULL DEFAULT 'gestor',
            texto TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_comentario_ticket (ticket_id),
            CONSTRAINT fk_comentario_ticket FOREIGN KEY (ticket_id)
                REFERENCES suporte_ticket (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`,
        `CREATE TABLE IF NOT EXISTS suporte_evento (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id BIGINT UNSIGNED NOT NULL,
            evento VARCHAR(50) NOT NULL,
            descricao VARCHAR(500) NOT NULL DEFAULT '',
            autor VARCHAR(150) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_evento_ticket (ticket_id),
            CONSTRAINT fk_evento_ticket FOREIGN KEY (ticket_id)
                REFERENCES suporte_ticket (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`,
        `CREATE TABLE IF NOT EXISTS suporte_setor (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            origem_setor_id INT NOT NULL,
            nome VARCHAR(150) NOT NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_origem_setor (origem_setor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`,
        `CREATE TABLE IF NOT EXISTS suporte_config (
            chave VARCHAR(100) NOT NULL,
            valor TEXT,
            atualizado_por VARCHAR(150) DEFAULT NULL,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (chave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
    ];
    sqls.forEach((sql) => {
        suporteQuery(sql, [])
            .then(() => console.log("[SUPORTE] Tabela verificada."))
            .catch((err) => console.error("[SUPORTE] Erro ao criar tabela:", err.message));
    });
}

// Migra tabelas existentes (bancos criados antes desta versão).
function migrarTabelasSuporte() {
    const migracoes = [
        "ALTER TABLE suporte_ticket ADD COLUMN user_email VARCHAR(190) NOT NULL DEFAULT ''",
        "ALTER TABLE suporte_ticket ADD COLUMN tipo ENUM('duvida','sugestao','bug') DEFAULT NULL",
        "ALTER TABLE suporte_ticket ADD COLUMN ssi_codigo VARCHAR(30) DEFAULT NULL",
        "ALTER TABLE suporte_ticket ADD COLUMN ssi_prioridade ENUM('urgente','proxima_atualizacao') DEFAULT NULL",
        "ALTER TABLE suporte_ticket ADD COLUMN atendente_nome VARCHAR(150) DEFAULT NULL",
        "ALTER TABLE suporte_ticket ADD COLUMN aceito_em DATETIME DEFAULT NULL",
        "ALTER TABLE suporte_ticket ADD COLUMN fechado_em DATETIME DEFAULT NULL",
        "ALTER TABLE suporte_ticket MODIFY status ENUM('aberto','em_analise','em_andamento','aguardando_cliente','resolvido','cancelado','reaberto','encaminhado_ssi','teste_interno') NOT NULL DEFAULT 'aberto'",
        "ALTER TABLE suporte_ticket ADD COLUMN setor_id BIGINT UNSIGNED DEFAULT NULL",
        "ALTER TABLE suporte_ticket ADD COLUMN setor_nome VARCHAR(150) DEFAULT NULL",
        "ALTER TABLE suporte_ticket ADD COLUMN prioridade ENUM('baixa','media','alta','urgente') NOT NULL DEFAULT 'media'",
        "ALTER TABLE suporte_arquivo ADD COLUMN tipo ENUM('imagem','video','documento','audio') NOT NULL DEFAULT 'imagem'",
        "ALTER TABLE suporte_arquivo MODIFY tipo ENUM('imagem','video','documento','audio') NOT NULL DEFAULT 'imagem'"
    ];
    const roda = (sql) => {
        suporteQuery(sql, [])
            .then(() => console.log("[SUPORTE] Migração OK: " + sql.slice(0, 60)))
            .catch((err) => {
                if (err && err.code === "ER_DUP_FIELDNAME") {
                    console.log("[SUPORTE] Coluna já existente (ignorado).");
                } else {
                    console.error("[SUPORTE] Migração falhou: " + err.message);
                }
            });
    };
    migracoes.forEach(roda);
}

function suporteQuery(sql, params) {
    return new Promise((resolve, reject) => {
        conectarSuporte();
        if (!SUPORTE.db) {
            return reject(new Error("Banco externo de suporte indisponível."));
        }
        SUPORTE.db.query(sql, params, (err, results) => {
            if (err) reject(err);
            else resolve(results);
        });
    });
}

// Registra evento na timeline do chamado (nunca derruba a requisição).
function registrarEventoSuporte(ticketId, evento, descricao, autor) {
    suporteQuery(
        "INSERT INTO suporte_evento (ticket_id, evento, descricao, autor) VALUES (?, ?, ?, ?)",
        [ticketId, evento, String(descricao || "").slice(0, 500), String(autor || "").slice(0, 150)]
    ).catch((err) => console.error("[SUPORTE] Erro ao registrar evento:", err.message));
}

function b64urlEncode(buffer) {
    return buffer.toString("base64").replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}

function b64urlDecode(str) {
    let s = str.replace(/-/g, "+").replace(/_/g, "/");
    while (s.length % 4) s += "=";
    return Buffer.from(s, "base64");
}

// Valida o token assinado gerado pelo widget PHP (mesmo algoritmo).
function validarTokenSuporte(authHeader) {
    const match = /^Bearer\s+(.+)$/i.exec(authHeader || "");
    if (!match) return null;
    const parts = match[1].split(".");
    if (parts.length !== 2) return null;

    let payload;
    try {
        payload = JSON.parse(b64urlDecode(parts[0]).toString("utf8"));
    } catch (e) {
        return null;
    }
    if (!payload || !payload.empresa || !payload.uid || !payload.ulogin || !payload.exp) return null;
    if (parseInt(payload.exp, 10) < Math.floor(Date.now() / 1000)) return null;
    if (!/^[a-z0-9_]{2,60}$/i.test(payload.empresa)) return null;

    const keyEmpresa = crypto.createHmac("sha256", SUPORTE.apiKey).update("techps_suporte|" + payload.empresa).digest();
    const esperado = b64urlEncode(crypto.createHmac("sha256", keyEmpresa).update(parts[0]).digest());
    if (esperado !== parts[1]) return null;

    return payload;
}

// MIME real do arquivo (magic bytes) — nunca confiar no Content-Type enviado.
function mimeReal(buffer) {
    if (!buffer || buffer.length < 12) return null;
    if (buffer[0] === 0xFF && buffer[1] === 0xD8 && buffer[2] === 0xFF) return "image/jpeg";
    if (buffer[0] === 0x89 && buffer[1] === 0x50 && buffer[2] === 0x4E && buffer[3] === 0x47) return "image/png";
    if (buffer[0] === 0x47 && buffer[1] === 0x49 && buffer[2] === 0x46 && buffer[3] === 0x38) return "image/gif";
    if (buffer.toString("ascii", 0, 4) === "RIFF" && buffer.toString("ascii", 8, 12) === "WEBP") return "image/webp";
    return null;
}

// Assinatura real de vídeo (magic bytes) — mp4/mov usam a mesma box "ftyp".
function assinaturaVideo(buffer) {
    if (!buffer || buffer.length < 12) return null;
    if (buffer.toString("ascii", 4, 8) === "ftyp") return "video"; // mp4 / mov (qtff)
    if (buffer[0] === 0x1A && buffer[1] === 0x45 && buffer[2] === 0xDF && buffer[3] === 0xA3) return "video"; // webm (EBML)
    return null;
}

// Assinatura real de áudio (magic bytes). weba/m4a reaproveitam a checagem de vídeo
// porque compartilham o mesmo container (EBML/WebM e MP4 "ftyp"), só o conteúdo muda.
function assinaturaAudio(buffer, ext) {
    if (!buffer || buffer.length < 4) return false;
    if (ext === "mp3") {
        if (buffer.toString("ascii", 0, 3) === "ID3") return true;
        return buffer[0] === 0xFF && (buffer[1] & 0xE0) === 0xE0; // frame sync sem tag ID3
    }
    if (ext === "wav") {
        return buffer.length >= 12 && buffer.toString("ascii", 0, 4) === "RIFF" && buffer.toString("ascii", 8, 12) === "WAVE";
    }
    if (ext === "oga" || ext === "ogg") {
        return buffer.toString("ascii", 0, 4) === "OggS";
    }
    if (ext === "weba" || ext === "m4a") {
        return !!assinaturaVideo(buffer); // EBML (0x1A45DFA3) ou box "ftyp"
    }
    return false;
}

// Assinatura real de documento (magic bytes).
function assinaturaDocumento(buffer) {
    if (!buffer || buffer.length < 8) return null;
    if (buffer.toString("ascii", 0, 4) === "%PDF") return "pdf";
    if (buffer[0] === 0xD0 && buffer[1] === 0xCF && buffer[2] === 0x11 && buffer[3] === 0xE0 &&
        buffer[4] === 0xA1 && buffer[5] === 0xB1 && buffer[6] === 0x1A && buffer[7] === 0xE1) return "ole"; // doc/xls/ppt legado
    if (buffer[0] === 0x50 && buffer[1] === 0x4B && buffer[2] === 0x03 && buffer[3] === 0x04) return "zip"; // docx/xlsx/pptx
    return null;
}

// Texto simples (txt/csv) sem assinatura mágica confiável: heurística — sem bytes nulos/controle nos primeiros KB.
function pareceTexto(buffer) {
    if (!buffer || !buffer.length) return false;
    const amostra = buffer.subarray(0, Math.min(buffer.length, 2048));
    for (let i = 0; i < amostra.length; i++) {
        const b = amostra[i];
        if (b === 0x00 || (b < 0x09) || (b > 0x0D && b < 0x20)) return false;
    }
    return true;
}

// Classifica um arquivo recebido (extensão do nome original + assinatura real do conteúdo).
// Retorna { categoria: 'imagem'|'video'|'documento', mime, ext } ou null se não bater com nada permitido.
function categorizarArquivo(nomeOriginal, buffer) {
    const nome = String(nomeOriginal || "").toLowerCase();
    const pontoIdx = nome.lastIndexOf(".");
    const ext = pontoIdx >= 0 ? nome.slice(pontoIdx + 1) : "";

    if (SUPORTE.extImagem.includes(ext)) {
        const mime = mimeReal(buffer);
        if (mime && SUPORTE.mimeExt[mime]) {
            return { categoria: "imagem", mime, ext: SUPORTE.mimeExt[mime] };
        }
        return null;
    }

    if (SUPORTE.extVideo.includes(ext)) {
        if (!assinaturaVideo(buffer)) return null;
        const mime = ext === "webm" ? "video/webm" : (ext === "mov" ? "video/quicktime" : "video/mp4");
        return { categoria: "video", mime, ext };
    }

    if (SUPORTE.extAudio.includes(ext)) {
        if (!assinaturaAudio(buffer, ext)) return null;
        const mimePorExt = { mp3: "audio/mpeg", wav: "audio/wav", oga: "audio/ogg", ogg: "audio/ogg", m4a: "audio/mp4", weba: "audio/webm" };
        return { categoria: "audio", mime: mimePorExt[ext], ext };
    }

    if (SUPORTE.extDocumento.includes(ext)) {
        if (ext === "txt" || ext === "csv") {
            if (!pareceTexto(buffer)) return null;
            return { categoria: "documento", mime: ext === "csv" ? "text/csv" : "text/plain", ext };
        }
        const assinatura = assinaturaDocumento(buffer);
        if (ext === "pdf") {
            if (assinatura !== "pdf") return null;
            return { categoria: "documento", mime: "application/pdf", ext };
        }
        if (["doc", "xls", "ppt"].includes(ext)) {
            if (assinatura !== "ole") return null;
        } else if (["docx", "xlsx", "pptx"].includes(ext)) {
            if (assinatura !== "zip") return null;
        }
        const mimePorExt = {
            doc: "application/msword",
            xls: "application/vnd.ms-excel",
            ppt: "application/vnd.ms-powerpoint",
            docx: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            xlsx: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            pptx: "application/vnd.openxmlformats-officedocument.presentationml.presentation"
        };
        return { categoria: "documento", mime: mimePorExt[ext], ext };
    }

    return null;
}

function exigirAdminSuporte(req, res, next) {
    const chave = req.headers["x-api-key"] || "";
    if (!SUPORTE.adminKey || chave !== SUPORTE.adminKey) {
        return res.status(401).json({ ok: false, msg: "Acesso não autorizado." });
    }
    next();
}

const uploadSuporte = multer({
    storage: multer.memoryStorage(),
    limits: { fileSize: SUPORTE.maxBytesVideo, files: SUPORTE.maxArquivos }
});

// Cria chamado (widget → token Bearer)
app.post("/suporte/tickets", uploadSuporte.array("anexos", SUPORTE.maxArquivos), async (req, res) => {
    try {
        const payload = validarTokenSuporte(req.headers.authorization);
        if (!payload) {
            return res.status(401).json({ ok: false, msg: "Sessão expirada ou inválida. Recarregue a página e tente novamente." });
        }

        const empresa = String(payload.empresa);
        const empresaNome = String(payload.empresa_nome || empresa).slice(0, 150);
        const uid = String(payload.uid).slice(0, 50);
        const ulogin = String(payload.ulogin).slice(0, 100);
        const unome = String(payload.unome || "").slice(0, 150);
        const uemail = String(payload.user_email || "").slice(0, 190);
        const uemailValido = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(uemail) ? uemail : "";

        let descricao = String(req.body.descricao || "").trim();
        let paginaUrl = String(req.body.pagina_url || "").trim();
        descricao = descricao.replace(/[\x00-\x1F\x7F]/g, "");
        paginaUrl = paginaUrl.replace(/[\x00-\x1F\x7F]/g, "");

        if (descricao.length < SUPORTE.minDescricao || descricao.length > SUPORTE.maxDescricao) {
            return res.status(400).json({ ok: false, msg: "Descreva o problema (entre 5 e 2000 caracteres)." });
        }
        if (paginaUrl.length > SUPORTE.maxUrl) {
            return res.status(400).json({ ok: false, msg: "URL da página muito longa." });
        }

        // Setor do chamado (opcional apenas se não houver nenhum setor ativo cadastrado).
        let setorId = null;
        let setorNome = null;
        const setorIdInformado = parseInt(req.body.setor_id, 10);
        if (setorIdInformado && setorIdInformado > 0) {
            const setorRows = await suporteQuery(
                "SELECT id, nome FROM suporte_setor WHERE id = ? AND status = 'ativo'",
                [setorIdInformado]
            );
            if (!setorRows.length) {
                return res.status(400).json({ ok: false, msg: "Setor selecionado é inválido ou não está mais disponível." });
            }
            setorId = setorRows[0].id;
            setorNome = setorRows[0].nome;
        } else {
            const totalSetoresAtivos = await suporteQuery("SELECT COUNT(*) AS total FROM suporte_setor WHERE status = 'ativo'", []);
            if (parseInt(totalSetoresAtivos[0].total, 10) > 0) {
                return res.status(400).json({ ok: false, msg: "Selecione o setor de destino do chamado." });
            }
        }

        // Valida anexos: imagem/documento até 5MB, vídeo até 25MB (máx. 1 vídeo), áudio até 8MB (máx. 2), conteúdo real conferido por assinatura.
        const arquivos = [];
        let totalVideos = 0;
        let totalAudios = 0;
        if (req.files && req.files.length) {
            for (const file of req.files) {
                const nomeOriginal = String(file.originalname || "").slice(0, 200);
                const categorizado = categorizarArquivo(nomeOriginal, file.buffer);
                if (!categorizado) {
                    return res.status(400).json({ ok: false, msg: "Arquivo \"" + nomeOriginal + "\" não é um tipo permitido (imagem, vídeo, áudio ou documento)." });
                }
                const tetoBytes = categorizado.categoria === "video" ? SUPORTE.maxBytesVideo
                    : categorizado.categoria === "audio" ? SUPORTE.maxBytesAudio
                    : SUPORTE.maxBytesPadrao;
                if (file.size > tetoBytes) {
                    return res.status(400).json({ ok: false, msg: "Arquivo \"" + nomeOriginal + "\" excede " + (tetoBytes / (1024 * 1024)) + "MB." });
                }
                if (categorizado.categoria === "video") {
                    totalVideos++;
                    if (totalVideos > SUPORTE.maxVideos) {
                        return res.status(400).json({ ok: false, msg: "Permitido no máximo " + SUPORTE.maxVideos + " vídeo por chamado." });
                    }
                }
                if (categorizado.categoria === "audio") {
                    totalAudios++;
                    if (totalAudios > SUPORTE.maxAudios) {
                        return res.status(400).json({ ok: false, msg: "Permitido no máximo " + SUPORTE.maxAudios + " áudio(s) por chamado." });
                    }
                }
                arquivos.push({
                    nomeOriginal: nomeOriginal,
                    mime: categorizado.mime,
                    ext: categorizado.ext,
                    categoria: categorizado.categoria,
                    tamanho: file.size,
                    buffer: file.buffer
                });
            }
        }

        // Rate limit: máx. 20 chamados/dia por usuário/empresa.
        const rateRows = await suporteQuery(
            "SELECT COUNT(*) AS total FROM suporte_rate WHERE empresa_key = ? AND user_id = ? AND created_at >= CURDATE()",
            [empresa, uid]
        );
        if (parseInt(rateRows[0].total, 10) >= SUPORTE.rateLimitDia) {
            return res.status(429).json({ ok: false, msg: "Limite diário de chamados atingido. Tente novamente amanhã." });
        }

        const ins = await suporteQuery(
            "INSERT INTO suporte_ticket (empresa_key, empresa_nome, user_id, user_login, user_nome, user_email, pagina_url, descricao, setor_id, setor_nome) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [empresa, empresaNome, uid, ulogin, unome, uemailValido, paginaUrl, descricao, setorId, setorNome]
        );
        const ticketId = ins.insertId;

        // Salva imagens em disco (nomes gerados pelo servidor).
        let salvos = 0;
        if (arquivos.length) {
            const dirTicket = path.join(SUPORTE.uploadDir, empresa, String(ticketId));
            fs.mkdirSync(dirTicket, { recursive: true });
            for (const a of arquivos) {
                const nomeGerado = ticketId + "_" + crypto.randomBytes(4).toString("hex") + "." + a.ext;
                const caminhoAbsoluto = path.join(dirTicket, nomeGerado);
                fs.writeFileSync(caminhoAbsoluto, a.buffer);
                await suporteQuery(
                    "INSERT INTO suporte_arquivo (ticket_id, nome_original, nome_gerado, mime, tamanho_bytes, caminho, tipo) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [ticketId, a.nomeOriginal, nomeGerado, a.mime, a.tamanho, path.join(empresa, String(ticketId), nomeGerado), a.categoria]
                );
                salvos++;
            }
        }

        await suporteQuery("INSERT INTO suporte_rate (empresa_key, user_id) VALUES (?, ?)", [empresa, uid]);

        // Timeline: abertura do chamado.
        registrarEventoSuporte(ticketId, "aberto", "Chamado aberto pelo cliente", unome || ulogin);

        // E-mail de abertura do chamado.
        if (uemailValido) {
            enviarEmailSuporte(
                uemailValido,
                "Chamado #" + ticketId + " aberto com sucesso — TechPS",
                htmlEmailSuporte({
                    id: ticketId,
                    empresa_key: empresa,
                    empresa_nome: empresaNome,
                    status: "aberto",
                    descricao: descricao,
                    atendente_nome: ""
                }, "Seu chamado foi aberto!", "Nossa equipe de suporte irá analisar e retornar por aqui. Acompanhe o status pelo sistema.")
            );
        }

        // Aviso interno: e-mail(s) cadastrados em Gestão de Suporte → Configurações.
        const emailsNotificacao = await obterConfigSuporte("emails_notificacao");
        if (emailsNotificacao) {
            enviarEmailSuporte(
                emailsNotificacao,
                "Novo chamado #" + ticketId + " — " + empresaNome,
                htmlEmailNotificacaoInterna({
                    id: ticketId,
                    empresa_key: empresa,
                    empresa_nome: empresaNome,
                    setor_nome: setorNome,
                    user_nome: unome,
                    user_login: ulogin,
                    descricao: descricao
                })
            );
        }

        res.status(201).json({ ok: true, ticket_id: ticketId, msg: "Chamado aberto com sucesso." });
    } catch (err) {
        console.error("[SUPORTE] Erro ao abrir chamado:", err);
        res.status(500).json({ ok: false, msg: "Erro interno ao registrar o chamado. Tente novamente em instantes." });
    }
});

// Lista chamados (painel de gestão — exige x-api-key)
app.get("/suporte/tickets", exigirAdminSuporte, async (req, res) => {
    try {
        const empresa = String(req.query.empresa || "").trim();
        const status = String(req.query.status || "").trim();
        const prioridade = String(req.query.prioridade || "").trim();
        const dataInicio = String(req.query.data_inicio || "").trim();
        const dataFim = String(req.query.data_fim || "").trim();
        const limite = Math.min(parseInt(req.query.limit || "50", 10) || 50, 100);
        const pagina = Math.max(parseInt(req.query.pagina || "1", 10) || 1, 1);
        const offset = (pagina - 1) * limite;

        const setorIdFiltro = parseInt(req.query.setor_id, 10);

        let where = [];
        let params = [];
        if (empresa) { where.push("empresa_key = ?"); params.push(empresa); }
        if (setorIdFiltro && setorIdFiltro > 0) { where.push("setor_id = ?"); params.push(setorIdFiltro); }
        if (status && SUPORTE_STATUS[status]) { where.push("status = ?"); params.push(status); }
        if (prioridade && SUPORTE_PRIORIDADES[prioridade]) { where.push("prioridade = ?"); params.push(prioridade); }
        if (dataInicio && /^\d{4}-\d{2}-\d{2}$/.test(dataInicio)) { where.push("created_at >= ?"); params.push(dataInicio + " 00:00:00"); }
        if (dataFim && /^\d{4}-\d{2}-\d{2}$/.test(dataFim)) { where.push("created_at <= ?"); params.push(dataFim + " 23:59:59"); }
        const filtro = where.length ? "WHERE " + where.join(" AND ") : "";

        const linhas = await suporteQuery(
            "SELECT id, empresa_key, empresa_nome, user_id, user_login, user_nome, user_email, pagina_url, descricao, status, tipo, prioridade, ssi_codigo, ssi_prioridade, atendente_nome, aceito_em, fechado_em, created_at, setor_id, setor_nome FROM suporte_ticket " + filtro + " ORDER BY created_at DESC LIMIT ? OFFSET ?",
            params.concat([limite, offset])
        );
        const totalRows = await suporteQuery(
            "SELECT COUNT(*) AS total FROM suporte_ticket " + filtro,
            params
        );

        res.json({ ok: true, total: parseInt(totalRows[0].total, 10), pagina, limite, tickets: linhas });
    } catch (err) {
        console.error("[SUPORTE] Erro ao listar chamados:", err);
        res.status(500).json({ ok: false, msg: "Erro ao listar chamados." });
    }
});

// Detalhe de um chamado + arquivos (painel de gestão)
app.get("/suporte/tickets/:id", exigirAdminSuporte, async (req, res) => {
    try {
        const id = parseInt(req.params.id, 10);
        if (!id || id < 1) return res.status(400).json({ ok: false, msg: "ID inválido." });

        const linhas = await suporteQuery(
            "SELECT id, empresa_key, empresa_nome, user_id, user_login, user_nome, user_email, pagina_url, descricao, status, tipo, prioridade, ssi_codigo, ssi_prioridade, atendente_nome, aceito_em, fechado_em, created_at, setor_id, setor_nome FROM suporte_ticket WHERE id = ?",
            [id]
        );
        if (!linhas.length) return res.status(404).json({ ok: false, msg: "Chamado não encontrado." });

        const arquivos = await suporteQuery(
            "SELECT id, nome_original, nome_gerado, mime, tamanho_bytes, tipo, created_at FROM suporte_arquivo WHERE ticket_id = ?",
            [id]
        );

        const comentarios = await suporteQuery(
            "SELECT id, autor, autor_login, autor_tipo, texto, created_at FROM suporte_comentario WHERE ticket_id = ? ORDER BY created_at ASC",
            [id]
        );

        const eventos = await suporteQuery(
            "SELECT id, evento, descricao, autor, created_at FROM suporte_evento WHERE ticket_id = ? ORDER BY created_at ASC",
            [id]
        );

        res.json({ ok: true, ticket: linhas[0], arquivos, comentarios, eventos });
    } catch (err) {
        console.error("[SUPORTE] Erro ao buscar chamado:", err);
        res.status(500).json({ ok: false, msg: "Erro ao buscar chamado." });
    }
});

// Lista empresas que possuem chamados (painel de gestão)
app.get("/suporte/empresas", exigirAdminSuporte, async (req, res) => {
    try {
        const linhas = await suporteQuery(
            "SELECT empresa_key, MAX(empresa_nome) AS empresa_nome, COUNT(*) AS total_chamados FROM suporte_ticket GROUP BY empresa_key ORDER BY empresa_nome ASC",
            []
        );
        res.json({ ok: true, empresas: linhas });
    } catch (err) {
        console.error("[SUPORTE] Erro ao listar empresas:", err);
        res.status(500).json({ ok: false, msg: "Erro ao listar empresas." });
    }
});

// Indicadores agregados do suporte (painel de gestão — dashboard).
// Aceita os mesmos filtros da listagem de chamados; agrega tudo em memória
// (volume de chamados é pequeno o bastante para não precisar de SQL agregado).
app.get("/suporte/dashboard", exigirAdminSuporte, async (req, res) => {
    try {
        const empresa = String(req.query.empresa || "").trim();
        const status = String(req.query.status || "").trim();
        const dataInicio = String(req.query.data_inicio || "").trim();
        const dataFim = String(req.query.data_fim || "").trim();
        const setorIdFiltro = parseInt(req.query.setor_id, 10);

        let where = [];
        let params = [];
        if (empresa) { where.push("empresa_key = ?"); params.push(empresa); }
        if (setorIdFiltro && setorIdFiltro > 0) { where.push("setor_id = ?"); params.push(setorIdFiltro); }
        if (status && SUPORTE_STATUS[status]) { where.push("status = ?"); params.push(status); }
        if (dataInicio && /^\d{4}-\d{2}-\d{2}$/.test(dataInicio)) { where.push("created_at >= ?"); params.push(dataInicio + " 00:00:00"); }
        if (dataFim && /^\d{4}-\d{2}-\d{2}$/.test(dataFim)) { where.push("created_at <= ?"); params.push(dataFim + " 23:59:59"); }
        const filtro = where.length ? "WHERE " + where.join(" AND ") : "";

        const linhas = await suporteQuery(
            "SELECT id, empresa_key, empresa_nome, setor_nome, status, tipo, prioridade, pagina_url, created_at, aceito_em, fechado_em " +
            "FROM suporte_ticket " + filtro + " ORDER BY created_at ASC",
            params
        );

        const slaHorasPorPrioridade = {};
        for (const p of Object.keys(SUPORTE_PRIORIDADES)) {
            const bruto = await obterConfigSuporte("sla_" + p + "_horas");
            slaHorasPorPrioridade[p] = /^\d+$/.test(bruto) ? parseInt(bruto, 10) : null;
        }

        const STATUS_ABERTOS = new Set(["aberto", "em_analise", "em_andamento", "aguardando_cliente", "reaberto", "encaminhado_ssi", "teste_interno"]);

        const normalizarPagina = (url) => {
            let s = String(url || "").trim();
            if (!s) return "(sem página)";
            s = s.split("#")[0].split("?")[0];
            s = s.replace(/^https?:\/\/[^/]+/i, "");
            if (!s.startsWith("/")) s = "/" + s;
            return s.length > 80 ? s.slice(0, 77) + "..." : s;
        };

        const contagemStatus = {};
        const contagemTipo = {};
        const contagemPrioridade = {};
        const contagemEmpresa = {};
        const contagemPagina = {};
        const contagemSetor = {};
        const contagemDia = {};
        let somaResolucaoHoras = 0, qtdResolucao = 0;
        let somaAceiteHoras = 0, qtdAceite = 0;
        let slaDentroPrazo = 0, slaAtrasado = 0, slaSemConfig = 0;
        const abertosDetalhe = [];

        for (const t of linhas) {
            contagemStatus[t.status] = (contagemStatus[t.status] || 0) + 1;

            const tipoKey = t.tipo || "nao_classificado";
            contagemTipo[tipoKey] = (contagemTipo[tipoKey] || 0) + 1;

            const prioridadeKey = t.prioridade || "media";
            contagemPrioridade[prioridadeKey] = (contagemPrioridade[prioridadeKey] || 0) + 1;

            const empresaKey = t.empresa_key;
            if (!contagemEmpresa[empresaKey]) {
                contagemEmpresa[empresaKey] = { empresa_key: empresaKey, empresa_nome: t.empresa_nome || empresaKey, total: 0 };
            }
            contagemEmpresa[empresaKey].total++;

            const pagina = normalizarPagina(t.pagina_url);
            contagemPagina[pagina] = (contagemPagina[pagina] || 0) + 1;

            const setor = t.setor_nome || "Sem setor";
            contagemSetor[setor] = (contagemSetor[setor] || 0) + 1;

            const dataCriacao = new Date(t.created_at);
            const diaKey = dataCriacao.toISOString().slice(0, 10);
            contagemDia[diaKey] = (contagemDia[diaKey] || 0) + 1;

            if (t.fechado_em) {
                const horas = (new Date(t.fechado_em) - dataCriacao) / 3600000;
                if (horas >= 0) { somaResolucaoHoras += horas; qtdResolucao++; }
            }
            if (t.aceito_em) {
                const horas = (new Date(t.aceito_em) - dataCriacao) / 3600000;
                if (horas >= 0) { somaAceiteHoras += horas; qtdAceite++; }
            }

            // SLA: compara o prazo configurado pra essa prioridade contra o tempo até fechar
            // (ou, se ainda aberto, contra agora — pra sinalizar quem já está estourando o prazo).
            const slaHoras = slaHorasPorPrioridade[prioridadeKey];
            if (slaHoras === null) {
                slaSemConfig++;
            } else {
                const referencia = t.fechado_em ? new Date(t.fechado_em) : new Date();
                const horasDecorridas = (referencia - dataCriacao) / 3600000;
                if (horasDecorridas <= slaHoras) { slaDentroPrazo++; } else { slaAtrasado++; }
            }

            if (STATUS_ABERTOS.has(t.status)) {
                abertosDetalhe.push({
                    id: t.id,
                    empresa_key: t.empresa_key,
                    empresa_nome: t.empresa_nome,
                    status: t.status,
                    created_at: t.created_at,
                    dias_aberto: Math.floor((Date.now() - dataCriacao.getTime()) / 86400000)
                });
            }
        }

        abertosDetalhe.sort((a, b) => b.dias_aberto - a.dias_aberto);

        const topN = (obj, n, mapFn) => Object.entries(obj)
            .map(([chave, valor]) => mapFn(chave, valor))
            .sort((a, b) => b.total - a.total)
            .slice(0, n);

        // Tendência diária; se o período cobrir muitos dias, agrupa por mês para não poluir o gráfico.
        const dias = Object.keys(contagemDia).sort();
        let tendencia;
        if (dias.length > 60) {
            const contagemMes = {};
            for (const [dia, qtd] of Object.entries(contagemDia)) {
                const mes = dia.slice(0, 7);
                contagemMes[mes] = (contagemMes[mes] || 0) + qtd;
            }
            tendencia = Object.entries(contagemMes)
                .sort((a, b) => a[0].localeCompare(b[0]))
                .map(([mes, qtd]) => ({ periodo: mes, total: qtd }));
        } else {
            tendencia = dias.map((dia) => ({ periodo: dia, total: contagemDia[dia] }));
        }

        res.json({
            ok: true,
            resumo: {
                total: linhas.length,
                abertos_agora: abertosDetalhe.length,
                resolvidos: contagemStatus["resolvido"] || 0,
                cancelados: contagemStatus["cancelado"] || 0,
                tempo_medio_resolucao_horas: qtdResolucao ? +(somaResolucaoHoras / qtdResolucao).toFixed(1) : null,
                tempo_medio_aceite_horas: qtdAceite ? +(somaAceiteHoras / qtdAceite).toFixed(1) : null
            },
            sla: { dentro_prazo: slaDentroPrazo, atrasado: slaAtrasado, sem_config: slaSemConfig },
            por_status: Object.entries(contagemStatus).map(([status, tot]) => ({ status, total: tot })).sort((a, b) => b.total - a.total),
            por_tipo: Object.entries(contagemTipo).map(([tipo, tot]) => ({ tipo, total: tot })).sort((a, b) => b.total - a.total),
            por_prioridade: Object.entries(contagemPrioridade).map(([prioridade, tot]) => ({ prioridade, total: tot })).sort((a, b) => b.total - a.total),
            por_empresa: Object.values(contagemEmpresa).sort((a, b) => b.total - a.total).slice(0, 15),
            por_pagina: topN(contagemPagina, 15, (pagina, tot) => ({ pagina, total: tot })),
            por_setor: topN(contagemSetor, 15, (setor, tot) => ({ setor, total: tot })),
            tendencia,
            mais_antigos_abertos: abertosDetalhe.slice(0, 10)
        });
    } catch (err) {
        console.error("[SUPORTE] Erro ao gerar dashboard:", err);
        res.status(500).json({ ok: false, msg: "Erro ao gerar o dashboard." });
    }
});

// Cria/atualiza um setor de suporte (chamado por cadastro_setor.php quando marcado em /demo).
app.post("/suporte/setores", exigirAdminSuporte, async (req, res) => {
    try {
        const origemSetorId = parseInt(req.body.origem_setor_id, 10);
        const nome = String(req.body.nome || "").trim().slice(0, 150);
        const status = String(req.body.status || "").trim();
        if (!origemSetorId || origemSetorId < 1) {
            return res.status(400).json({ ok: false, msg: "origem_setor_id inválido." });
        }
        if (!nome) {
            return res.status(400).json({ ok: false, msg: "Nome do setor é obrigatório." });
        }
        if (!["ativo", "inativo"].includes(status)) {
            return res.status(400).json({ ok: false, msg: "Status deve ser ativo ou inativo." });
        }

        await suporteQuery(
            "INSERT INTO suporte_setor (origem_setor_id, nome, status) VALUES (?, ?, ?) " +
            "ON DUPLICATE KEY UPDATE nome = VALUES(nome), status = VALUES(status), updated_at = NOW()",
            [origemSetorId, nome, status]
        );

        res.json({ ok: true, msg: "Setor sincronizado." });
    } catch (err) {
        console.error("[SUPORTE] Erro ao sincronizar setor:", err);
        res.status(500).json({ ok: false, msg: "Erro ao sincronizar setor." });
    }
});

// Lista setores de suporte ativos (combo do widget e filtro da gestão).
// Aceita x-api-key (telas PHP server-side) OU Bearer token assinado do widget.
app.get("/suporte/setores", async (req, res) => {
    try {
        const chaveAdmin = req.headers["x-api-key"] || "";
        const autorizadoAdmin = SUPORTE.adminKey && chaveAdmin === SUPORTE.adminKey;
        const autorizadoToken = !autorizadoAdmin && !!validarTokenSuporte(req.headers.authorization);
        if (!autorizadoAdmin && !autorizadoToken) {
            return res.status(401).json({ ok: false, msg: "Acesso não autorizado." });
        }

        const linhas = await suporteQuery(
            "SELECT id, nome FROM suporte_setor WHERE status = 'ativo' ORDER BY nome ASC",
            []
        );
        res.json({ ok: true, setores: linhas });
    } catch (err) {
        console.error("[SUPORTE] Erro ao listar setores:", err);
        res.status(500).json({ ok: false, msg: "Erro ao listar setores." });
    }
});

// Chaves de configuração do SLA — uma por nível de prioridade, valor em horas corridas.
const SUPORTE_SLA_CAMPOS = ["sla_baixa_horas", "sla_media_horas", "sla_alta_horas", "sla_urgente_horas"];

async function salvarConfigSuporte(chave, valor, atualizadoPor) {
    await suporteQuery(
        "INSERT INTO suporte_config (chave, valor, atualizado_por) VALUES (?, ?, ?) " +
        "ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_por = VALUES(atualizado_por)",
        [chave, valor, atualizadoPor]
    );
}

// Lê as configurações gerais do suporte (e-mails de aviso de chamado novo + SLA por prioridade).
app.get("/suporte/config", exigirAdminSuporte, async (req, res) => {
    try {
        const config = { emails_notificacao: await obterConfigSuporte("emails_notificacao") };
        for (const campo of SUPORTE_SLA_CAMPOS) {
            config[campo] = await obterConfigSuporte(campo);
        }
        res.json({ ok: true, config });
    } catch (err) {
        console.error("[SUPORTE] Erro ao ler configurações:", err);
        res.status(500).json({ ok: false, msg: "Erro ao ler configurações." });
    }
});

// Atualiza as configurações gerais do suporte (e-mails de aviso + SLA por prioridade).
app.post("/suporte/config", exigirAdminSuporte, async (req, res) => {
    try {
        let emails = String(req.body.emails_notificacao || "").trim();
        const atualizadoPor = String(req.body.atualizado_por || "").slice(0, 150);

        // Valida cada e-mail informado (separados por vírgula).
        const lista = emails.split(",").map((e) => e.trim()).filter((e) => e !== "");
        for (const e of lista) {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) {
                return res.status(400).json({ ok: false, msg: "E-mail inválido: \"" + e + "\"." });
            }
        }
        emails = lista.join(", ");

        // SLA por prioridade: número inteiro de horas maior que zero, ou vazio (sem SLA definido pra essa prioridade).
        const slaValores = {};
        for (const campo of SUPORTE_SLA_CAMPOS) {
            const bruto = String(req.body[campo] || "").trim();
            if (bruto !== "" && (!/^\d+$/.test(bruto) || parseInt(bruto, 10) <= 0)) {
                return res.status(400).json({ ok: false, msg: "SLA de \"" + campo + "\" deve ser um número inteiro de horas maior que zero." });
            }
            slaValores[campo] = bruto;
        }

        await salvarConfigSuporte("emails_notificacao", emails, atualizadoPor);
        for (const campo of SUPORTE_SLA_CAMPOS) {
            await salvarConfigSuporte(campo, slaValores[campo], atualizadoPor);
        }

        res.json({ ok: true, msg: "Configurações salvas.", config: { emails_notificacao: emails, ...slaValores } });
    } catch (err) {
        console.error("[SUPORTE] Erro ao salvar configurações:", err);
        res.status(500).json({ ok: false, msg: "Erro ao salvar configurações." });
    }
});

// Adiciona comentário ao chamado.
// Autor gestor: x-api-key (painel TechPS) | Autor empresa: Bearer token (widget/app, com tenant check)
app.post("/suporte/tickets/:id/comentarios", async (req, res) => {
    try {
        const id = parseInt(req.params.id, 10);
        if (!id || id < 1) return res.status(400).json({ ok: false, msg: "ID inválido." });

        let texto = String(req.body.texto || "").trim();
        texto = texto.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, "");
        if (texto.length < 1 || texto.length > 1000) {
            return res.status(400).json({ ok: false, msg: "Comentário deve ter entre 1 e 1000 caracteres." });
        }

        let autor = "";
        let autorLogin = "";
        let autorTipo = "";

        const chaveAdmin = req.headers["x-api-key"] || "";
        if (SUPORTE.adminKey && chaveAdmin === SUPORTE.adminKey) {
            // Gestor TechPS
            autor = String(req.body.autor || "Gestor TechPS").slice(0, 150);
            autorLogin = String(req.body.autor_login || "").slice(0, 100);
            autorTipo = "gestor";
        } else {
            // Empresa (token assinado do widget/app) — valida que o chamado é da própria empresa
            const payload = validarTokenSuporte(req.headers.authorization);
            if (!payload) {
                return res.status(401).json({ ok: false, msg: "Acesso não autorizado." });
            }
            const chk = await suporteQuery("SELECT empresa_key FROM suporte_ticket WHERE id = ?", [id]);
            if (!chk.length) return res.status(404).json({ ok: false, msg: "Chamado não encontrado." });
            if (chk[0].empresa_key !== payload.empresa) {
                return res.status(403).json({ ok: false, msg: "Este chamado pertence a outra empresa." });
            }
            autor = String(payload.unome || payload.ulogin || "Usuário").slice(0, 150);
            autorLogin = String(payload.ulogin || "").slice(0, 100);
            autorTipo = "empresa";
        }

        const ins = await suporteQuery(
            "INSERT INTO suporte_comentario (ticket_id, autor, autor_login, autor_tipo, texto) VALUES (?, ?, ?, ?, ?)",
            [id, autor, autorLogin, autorTipo, texto]
        );

        // E-mail ao cliente quando o suporte (gestor) responde.
        if (autorTipo === "gestor") {
            const chkMail = await suporteQuery("SELECT * FROM suporte_ticket WHERE id = ?", [id]);
            if (chkMail.length && chkMail[0].user_email) {
                enviarEmailSuporte(
                    chkMail[0].user_email,
                    "Nova resposta no chamado #" + id + " — TechPS",
                    htmlEmailSuporte(chkMail[0], "Nova resposta da equipe TechPS", "Resposta de " + escH(autor) + ":<br><div style='background:#f7f7f7;border:1px solid #eee;border-radius:6px;padding:10px;white-space:pre-wrap;'>" + escH(texto) + "</div>")
                );
            }
            // Timeline: resposta do suporte.
            registrarEventoSuporte(id, "comentario_gestor", "Resposta do suporte", autor);
        } else {
            // Timeline: resposta do cliente.
            registrarEventoSuporte(id, "comentario_empresa", "Resposta do cliente", autor);
        }

        res.status(201).json({ ok: true, comentario_id: ins.insertId, msg: "Comentário adicionado." });
    } catch (err) {
        console.error("[SUPORTE] Erro ao adicionar comentário:", err);
        res.status(500).json({ ok: false, msg: "Erro ao adicionar comentário." });
    }
});

// Serve a imagem de um chamado (por id do arquivo — sem path traversal)
app.get("/suporte/tickets/:id/arquivos/:arquivoId", exigirAdminSuporte, async (req, res) => {
    try {
        const id = parseInt(req.params.id, 10);
        const arquivoId = parseInt(req.params.arquivoId, 10);
        if (!id || id < 1 || !arquivoId || arquivoId < 1) {
            return res.status(400).json({ ok: false, msg: "Parâmetros inválidos." });
        }

        const linhas = await suporteQuery(
            "SELECT caminho, mime FROM suporte_arquivo WHERE id = ? AND ticket_id = ?",
            [arquivoId, id]
        );
        if (!linhas.length) return res.status(404).json({ ok: false, msg: "Arquivo não encontrado." });

        const relativo = path.normalize(String(linhas[0].caminho));
        const absoluto = path.resolve(SUPORTE.uploadDir, relativo);
        if (!absoluto.startsWith(path.resolve(SUPORTE.uploadDir))) {
            return res.status(400).json({ ok: false, msg: "Caminho inválido." });
        }
        if (!fs.existsSync(absoluto)) return res.status(404).json({ ok: false, msg: "Arquivo não encontrado no storage." });

        res.sendFile(absoluto);
    } catch (err) {
        console.error("[SUPORTE] Erro ao servir arquivo:", err);
        res.status(500).json({ ok: false, msg: "Erro ao servir arquivo." });
    }
});

// Aceita o chamado (atendente assume o atendimento)
app.post("/suporte/tickets/:id/aceitar", exigirAdminSuporte, async (req, res) => {
    try {
        const id = parseInt(req.params.id, 10);
        if (!id || id < 1) return res.status(400).json({ ok: false, msg: "ID inválido." });

        const atendente = String(req.body.atendente || "Atendente TechPS").slice(0, 150);
        const chk = await suporteQuery("SELECT * FROM suporte_ticket WHERE id = ?", [id]);
        if (!chk.length) return res.status(404).json({ ok: false, msg: "Chamado não encontrado." });

        const upd = await suporteQuery(
            "UPDATE suporte_ticket SET status = 'em_andamento', atendente_nome = ?, aceito_em = NOW() WHERE id = ?",
            [atendente, id]
        );
        if (!upd.affectedRows) return res.status(404).json({ ok: false, msg: "Chamado não encontrado." });

        // Timeline: aceite do chamado.
        registrarEventoSuporte(id, "aceito", "Atendimento iniciado pelo suporte (" + atendente + ")", atendente);

        if (chk[0].user_email) {
            enviarEmailSuporte(
                chk[0].user_email,
                "Chamado #" + id + " em atendimento — TechPS",
                htmlEmailSuporte({ ...chk[0], status: "em_andamento", atendente_nome: atendente }, "Seu chamado entrou em atendimento!", "O atendente " + escH(atendente) + " iniciou o atendimento do seu chamado.")
            );
        }

        res.json({ ok: true, msg: "Chamado aceito e em atendimento." });
    } catch (err) {
        console.error("[SUPORTE] Erro ao aceitar chamado:", err);
        res.status(500).json({ ok: false, msg: "Erro ao aceitar chamado." });
    }
});

// Classifica o chamado (dúvida / sugestão / bug)
app.post("/suporte/tickets/:id/tipo", exigirAdminSuporte, async (req, res) => {
    try {
        const id = parseInt(req.params.id, 10);
        const tipo = String(req.body.tipo || "").trim();
        if (!id || id < 1) return res.status(400).json({ ok: false, msg: "ID inválido." });
        if (!["duvida", "sugestao", "bug"].includes(tipo)) {
            return res.status(400).json({ ok: false, msg: "Tipo deve ser duvida, sugestao ou bug." });
        }
        const upd = await suporteQuery("UPDATE suporte_ticket SET tipo = ? WHERE id = ?", [tipo, id]);
        if (!upd.affectedRows) return res.status(404).json({ ok: false, msg: "Chamado não encontrado." });

        // Timeline: classificação do chamado.
        registrarEventoSuporte(id, "tipo", "Chamado classificado como " + (SUPORTE_TIPOS[tipo] || tipo), "Gestão TechPS");

        res.json({ ok: true, msg: "Tipo do chamado atualizado." });
    } catch (err) {
        console.error("[SUPORTE] Erro ao classificar chamado:", err);
        res.status(500).json({ ok: false, msg: "Erro ao classificar chamado." });
    }
});

// Altera a prioridade do chamado (baixa/média/alta/urgente) — disponível o fluxo inteiro,
// independente do status ou do tipo atual (trocar o tipo não reseta a prioridade).
app.post("/suporte/tickets/:id/prioridade", exigirAdminSuporte, async (req, res) => {
    try {
        const id = parseInt(req.params.id, 10);
        const prioridade = String(req.body.prioridade || "").trim();
        if (!id || id < 1) return res.status(400).json({ ok: false, msg: "ID inválido." });
        if (!SUPORTE_PRIORIDADES[prioridade]) {
            return res.status(400).json({ ok: false, msg: "Prioridade deve ser baixa, media, alta ou urgente." });
        }
        const upd = await suporteQuery("UPDATE suporte_ticket SET prioridade = ? WHERE id = ?", [prioridade, id]);
        if (!upd.affectedRows) return res.status(404).json({ ok: false, msg: "Chamado não encontrado." });

        // Timeline: mudança de prioridade.
        registrarEventoSuporte(id, "prioridade", "Prioridade alterada para " + SUPORTE_PRIORIDADES[prioridade], "Gestão TechPS");

        res.json({ ok: true, msg: "Prioridade do chamado atualizada." });
    } catch (err) {
        console.error("[SUPORTE] Erro ao alterar prioridade:", err);
        res.status(500).json({ ok: false, msg: "Erro ao alterar prioridade." });
    }
});

// Altera status do chamado (painel de gestão)
app.post("/suporte/tickets/:id/status", exigirAdminSuporte, async (req, res) => {
    try {
        const id = parseInt(req.params.id, 10);
        const status = String(req.body.status || "").trim();
        if (!id || id < 1) return res.status(400).json({ ok: false, msg: "ID inválido." });
        if (!SUPORTE_STATUS[status]) {
            return res.status(400).json({ ok: false, msg: "Status inválido." });
        }

        const chk = await suporteQuery("SELECT * FROM suporte_ticket WHERE id = ?", [id]);
        if (!chk.length) return res.status(404).json({ ok: false, msg: "Chamado não encontrado." });

        // Encaminhado a SSI: gera código e exige prioridade.
        let ssiCodigo = null;
        let ssiPrioridade = null;
        if (status === "encaminhado_ssi") {
            ssiPrioridade = String(req.body.ssi_prioridade || "").trim();
            if (!["urgente", "proxima_atualizacao"].includes(ssiPrioridade)) {
                return res.status(400).json({ ok: false, msg: "Informe a prioridade da SSI (urgente ou proxima_atualizacao)." });
            }
            ssiCodigo = "SSI-" + new Date().getFullYear() + "-" + crypto.randomInt(1000, 9999);
        }

        const upd = await suporteQuery(
            "UPDATE suporte_ticket SET status = ?, ssi_codigo = COALESCE(?, ssi_codigo), ssi_prioridade = COALESCE(?, ssi_prioridade), " +
            "fechado_em = CASE WHEN ? IN ('resolvido','cancelado') THEN NOW() WHEN ? IN ('aberto','reaberto') THEN NULL ELSE fechado_em END " +
            "WHERE id = ?",
            [status, ssiCodigo, ssiPrioridade, status, status, id]
        );
        if (!upd.affectedRows) return res.status(404).json({ ok: false, msg: "Chamado não encontrado." });

        // Timeline: mudança de status (com horário de fechamento).
        let descStatus = "Status alterado para " + (SUPORTE_STATUS[status] || status);
        if (status === "resolvido" || status === "cancelado") {
            descStatus += " — chamado fechado";
        } else if (status === "encaminhado_ssi") {
            descStatus += " (SSI " + (ssiCodigo || chk[0].ssi_codigo) + ")";
        }
        registrarEventoSuporte(id, "status", descStatus, "Gestão TechPS");

        const novoTicket = { ...chk[0], status, ssi_codigo: ssiCodigo || chk[0].ssi_codigo, ssi_prioridade: ssiPrioridade || chk[0].ssi_prioridade };

        // E-mails de status / encerramento.
        if (chk[0].user_email) {
            const ehEncerramento = (status === "resolvido" || status === "cancelado");
            const ehReaberto = (status === "reaberto");
            let titulo = "Chamado #" + id + " atualizado — TechPS";
            let avisos = "";
            if (ehEncerramento) {
                titulo = status === "resolvido" ? "Chamado #" + id + " resolvido — TechPS" : "Chamado #" + id + " cancelado — TechPS";
                avisos = "Este chamado foi encerrado. Se precisar de mais alguma coisa, é só abrir um novo chamado.";
            } else if (ehReaberto) {
                avisos = "O chamado foi reaberto e voltou para análise da equipe.";
            } else if (status === "aguardando_cliente") {
                avisos = "Estamos aguardando o seu retorno para dar continuidade ao atendimento.";
            } else if (status === "em_analise") {
                avisos = "Nossa equipe já começou a analisar o seu chamado.";
            } else if (status === "encaminhado_ssi") {
                avisos = "O chamado foi encaminhado ao setor de suporte interno (SSI " + novoTicket.ssi_codigo + "). " + (novoTicket.ssi_prioridade === "urgente" ? "Tratamento prioritário — solução urgente em produção." : "Será resolvido na próxima atualização do sistema.");
            } else if (status === "teste_interno") {
                avisos = "A correção já foi desenvolvida e está em teste interno pela nossa equipe antes de ser liberada.";
            }
            enviarEmailSuporte(chk[0].user_email, titulo, htmlEmailSuporte(novoTicket, titulo, avisos));
        }

        res.json({ ok: true, msg: "Status atualizado." });
    } catch (err) {
        console.error("[SUPORTE] Erro ao atualizar status:", err);
        res.status(500).json({ ok: false, msg: "Erro ao atualizar status." });
    }
});

// Health do módulo de suporte (sem autenticação)
app.get("/suporte/health", (req, res) => {
    res.json({ ok: true, bancoConfigurado: !!(process.env.SUPORTE_DB_HOST && process.env.SUPORTE_DB_USER && process.env.SUPORTE_DB_NAME) });
});

// Tratamento de erros de upload (multer) — não altera rotas existentes.
app.use((err, req, res, next) => {
    if (err instanceof multer.MulterError) {
        const msg = err.code === "LIMIT_FILE_SIZE"
            ? "Arquivo excede o tamanho máximo permitido (5MB para imagem/documento, 25MB para vídeo)."
            : err.code === "LIMIT_UNEXPECTED_FILE"
                ? "Arquivo enviado em campo inválido."
                : "Limite de anexos excedido (máx. " + SUPORTE.maxArquivos + ").";
        return res.status(400).json({ ok: false, msg });
    }
    console.error(err);
    res.status(500).json({ ok: false, msg: "Erro interno do servidor." });
});

// Cria as tabelas do banco externo ao iniciar (se configurado).
criarTabelasSuporte();
migrarTabelasSuporte();

// Configuração do servidor HTTP para aceitar requisições HTTP
const httpServer = http.createServer(app);

httpServer.listen(port, () => {
    console.log(`HTTP Server is running on http://localhost:${port}`); // Corrigido o template string
});
