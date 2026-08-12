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

const SUPORTE = {
    apiKey: process.env.SUPORTE_API_KEY || "",
    adminKey: process.env.SUPORTE_ADMIN_KEY || "",
    db: null,
    maxImagens: 5,
    maxBytes: 5 * 1024 * 1024,
    minDescricao: 5,
    maxDescricao: 2000,
    maxUrl: 500,
    rateLimitDia: 20,
    ttl: 300,
    mimeExt: {
        "image/jpeg": "jpg",
        "image/png": "png",
        "image/webp": "webp",
        "image/gif": "gif"
    },
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
            pagina_url VARCHAR(500) NOT NULL DEFAULT '',
            descricao TEXT NOT NULL,
            status ENUM('aberto','resolvido') NOT NULL DEFAULT 'aberto',
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
    ];
    sqls.forEach((sql) => {
        suporteQuery(sql, [])
            .then(() => console.log("[SUPORTE] Tabela verificada."))
            .catch((err) => console.error("[SUPORTE] Erro ao criar tabela:", err.message));
    });
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

function exigirAdminSuporte(req, res, next) {
    const chave = req.headers["x-api-key"] || "";
    if (!SUPORTE.adminKey || chave !== SUPORTE.adminKey) {
        return res.status(401).json({ ok: false, msg: "Acesso não autorizado." });
    }
    next();
}

const uploadSuporte = multer({
    storage: multer.memoryStorage(),
    limits: { fileSize: SUPORTE.maxBytes, files: SUPORTE.maxImagens }
});

// Cria chamado (widget → token Bearer)
app.post("/suporte/tickets", uploadSuporte.array("imagens", SUPORTE.maxImagens), async (req, res) => {
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

        // Valida imagens (máx. 5, 5MB, conteúdo real).
        const arquivos = [];
        if (req.files && req.files.length) {
            for (const file of req.files) {
                const mime = mimeReal(file.buffer);
                if (!mime || !SUPORTE.mimeExt[mime]) {
                    return res.status(400).json({ ok: false, msg: "Tipo de imagem não permitido (JPG, PNG, WEBP ou GIF)." });
                }
                arquivos.push({
                    nomeOriginal: String(file.originalname || "").slice(0, 200),
                    mime: mime,
                    ext: SUPORTE.mimeExt[mime],
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
            "INSERT INTO suporte_ticket (empresa_key, empresa_nome, user_id, user_login, user_nome, pagina_url, descricao) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [empresa, empresaNome, uid, ulogin, unome, paginaUrl, descricao]
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
                    "INSERT INTO suporte_arquivo (ticket_id, nome_original, nome_gerado, mime, tamanho_bytes, caminho) VALUES (?, ?, ?, ?, ?, ?)",
                    [ticketId, a.nomeOriginal, nomeGerado, a.mime, a.tamanho, path.join(empresa, String(ticketId), nomeGerado)]
                );
                salvos++;
            }
        }

        await suporteQuery("INSERT INTO suporte_rate (empresa_key, user_id) VALUES (?, ?)", [empresa, uid]);

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
        const dataInicio = String(req.query.data_inicio || "").trim();
        const dataFim = String(req.query.data_fim || "").trim();
        const limite = Math.min(parseInt(req.query.limit || "50", 10) || 50, 100);
        const pagina = Math.max(parseInt(req.query.pagina || "1", 10) || 1, 1);
        const offset = (pagina - 1) * limite;

        let where = [];
        let params = [];
        if (empresa) { where.push("empresa_key = ?"); params.push(empresa); }
        if (status === "aberto" || status === "resolvido") { where.push("status = ?"); params.push(status); }
        if (dataInicio && /^\d{4}-\d{2}-\d{2}$/.test(dataInicio)) { where.push("created_at >= ?"); params.push(dataInicio + " 00:00:00"); }
        if (dataFim && /^\d{4}-\d{2}-\d{2}$/.test(dataFim)) { where.push("created_at <= ?"); params.push(dataFim + " 23:59:59"); }
        const filtro = where.length ? "WHERE " + where.join(" AND ") : "";

        const linhas = await suporteQuery(
            "SELECT id, empresa_key, empresa_nome, user_id, user_login, user_nome, pagina_url, descricao, status, created_at FROM suporte_ticket " + filtro + " ORDER BY created_at DESC LIMIT ? OFFSET ?",
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
            "SELECT id, empresa_key, empresa_nome, user_id, user_login, user_nome, pagina_url, descricao, status, created_at FROM suporte_ticket WHERE id = ?",
            [id]
        );
        if (!linhas.length) return res.status(404).json({ ok: false, msg: "Chamado não encontrado." });

        const arquivos = await suporteQuery(
            "SELECT id, nome_original, nome_gerado, mime, tamanho_bytes, created_at FROM suporte_arquivo WHERE ticket_id = ?",
            [id]
        );

        const comentarios = await suporteQuery(
            "SELECT id, autor, autor_login, autor_tipo, texto, created_at FROM suporte_comentario WHERE ticket_id = ? ORDER BY created_at ASC",
            [id]
        );

        res.json({ ok: true, ticket: linhas[0], arquivos, comentarios });
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

// Altera status do chamado (painel de gestão)
app.post("/suporte/tickets/:id/status", exigirAdminSuporte, async (req, res) => {
    try {
        const id = parseInt(req.params.id, 10);
        const status = String(req.body.status || "").trim();
        if (!id || id < 1) return res.status(400).json({ ok: false, msg: "ID inválido." });
        if (status !== "aberto" && status !== "resolvido") {
            return res.status(400).json({ ok: false, msg: "Status deve ser 'aberto' ou 'resolvido'." });
        }

        const upd = await suporteQuery("UPDATE suporte_ticket SET status = ? WHERE id = ?", [status, id]);
        if (!upd.affectedRows) return res.status(404).json({ ok: false, msg: "Chamado não encontrado." });

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
            ? "Imagem excede o tamanho máximo de 5MB."
            : err.code === "LIMIT_UNEXPECTED_FILE"
                ? "Arquivo enviado em campo inválido."
                : "Limite de imagens excedido (máx. 5).";
        return res.status(400).json({ ok: false, msg });
    }
    console.error(err);
    res.status(500).json({ ok: false, msg: "Erro interno do servidor." });
});

// Cria as tabelas do banco externo ao iniciar (se configurado).
criarTabelasSuporte();

// Configuração do servidor HTTP para aceitar requisições HTTP
const httpServer = http.createServer(app);

httpServer.listen(port, () => {
    console.log(`HTTP Server is running on http://localhost:${port}`); // Corrigido o template string
});
