# TechPS Extrato — App de Cobrança

App web em **React + Vite** para gestão de extratos de cobrança por empresa, consumindo a API TechPS Jornada.

---

## Stack

| Camada | Tecnologia |
|---|---|
| Frontend | React 18 + Vite |
| Estilo | Tailwind CSS |
| Roteamento | React Router v6 |
| Estado global | Zustand |
| Requisições | Axios |
| Tabelas | TanStack Table v8 |
| Gráficos | Recharts |
| Formulários | React Hook Form + Zod |
| Banco local | MySQL (via backend próprio) |
| Backend | Node.js + Express + mysql2 |
| Hospedagem | cPanel (Node.js App) |

---

## Estrutura de Pastas

```
techps-extrato/
├── client/                        # Frontend React + Vite
│   ├── public/
│   ├── src/
│   │   ├── api/                   # Chamadas à API TechPS e ao backend
│   │   │   ├── techps.js          # Endpoints da API TechPS Jornada
│   │   │   └── backend.js         # Endpoints do backend local
│   │   ├── components/
│   │   │   ├── ui/                # Componentes base (Button, Input, Card, Modal)
│   │   │   ├── layout/            # Sidebar, Header, Layout
│   │   │   └── shared/            # SelectEmpresa, TabelaFuncionarios, etc.
│   │   ├── pages/
│   │   │   ├── Login.jsx
│   │   │   ├── Dashboard.jsx
│   │   │   ├── Empresas.jsx
│   │   │   ├── Funcionarios.jsx
│   │   │   ├── Placas.jsx
│   │   │   ├── CadastroCobranca.jsx
│   │   │   └── Extratos.jsx
│   │   ├── store/                 # Zustand stores
│   │   │   ├── authStore.js
│   │   │   └── empresaStore.js
│   │   ├── hooks/                 # Custom hooks
│   │   ├── utils/                 # Formatadores (CPF, CNPJ, moeda)
│   │   ├── App.jsx
│   │   └── main.jsx
│   ├── .env
│   ├── index.html
│   ├── vite.config.js
│   └── tailwind.config.js
│
├── server/                        # Backend Node.js + Express
│   ├── src/
│   │   ├── config/
│   │   │   └── db.js              # Conexão MySQL
│   │   ├── routes/
│   │   │   ├── auth.js            # Login / logout
│   │   │   ├── cobranca.js        # CRUD de cobranças
│   │   │   ├── valores.js         # Valores por ocupação e placa
│   │   │   └── extrato.js         # Geração de extrato
│   │   ├── middleware/
│   │   │   └── auth.js            # JWT middleware
│   │   └── app.js
│   ├── .env
│   ├── package.json
│   └── app.js                     # Entry point para cPanel
│
└── README.md
```

---

## Banco de Dados MySQL (Backend)

```sql
-- Usuários do sistema
CREATE TABLE usuarios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    senha_hash  VARCHAR(255) NOT NULL,
    nivel       ENUM('admin','operador') DEFAULT 'operador',
    status      ENUM('ativo','inativo') DEFAULT 'ativo',
    criado_em   DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Configuração de acesso à API TechPS por domínio
CREATE TABLE dominios_api (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100) NOT NULL,
    url_base    VARCHAR(255) NOT NULL,   -- ex: https://cliente.com.br/armazem_paraiba/contabilidade/api.php
    api_key     VARCHAR(255) NOT NULL,
    status      ENUM('ativo','inativo') DEFAULT 'ativo',
    criado_em   DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de preços por ocupação (por domínio + empresa)
CREATE TABLE valores_ocupacao (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    dominio_id      INT NOT NULL,
    empresa_id      INT NOT NULL,           -- ID da empresa na API TechPS
    empresa_cnpj    VARCHAR(20),
    ocupacao        VARCHAR(100) NOT NULL,  -- ex: Motorista, Ajudante
    valor_unitario  DECIMAL(10,2) NOT NULL,
    criado_em       DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dominio_empresa_ocupacao (dominio_id, empresa_id, ocupacao)
);

-- Tabela de preços por placa (por domínio + empresa)
CREATE TABLE valores_placa (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    dominio_id      INT NOT NULL,
    empresa_id      INT NOT NULL,
    empresa_cnpj    VARCHAR(20),
    placa           VARCHAR(10) NOT NULL,
    veiculo         VARCHAR(100),
    valor_unitario  DECIMAL(10,2) NOT NULL,
    criado_em       DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dominio_empresa_placa (dominio_id, empresa_id, placa)
);

-- Produtos extras de cobrança (ex: licença, suporte, etc.)
CREATE TABLE produtos_cobranca (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    dominio_id      INT NOT NULL,
    empresa_id      INT NOT NULL,
    descricao       VARCHAR(200) NOT NULL,
    valor_unitario  DECIMAL(10,2) NOT NULL,
    quantidade      INT DEFAULT 1,
    criado_em       DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Extratos gerados
CREATE TABLE extratos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    dominio_id      INT NOT NULL,
    empresa_id      INT NOT NULL,
    empresa_nome    VARCHAR(150),
    empresa_cnpj    VARCHAR(20),
    mes_referencia  DATE NOT NULL,          -- primeiro dia do mês
    total_funcionarios INT DEFAULT 0,
    total_placas    INT DEFAULT 0,
    valor_funcionarios DECIMAL(10,2) DEFAULT 0,
    valor_placas    DECIMAL(10,2) DEFAULT 0,
    valor_extras    DECIMAL(10,2) DEFAULT 0,
    valor_total     DECIMAL(10,2) DEFAULT 0,
    status          ENUM('rascunho','emitido','pago') DEFAULT 'rascunho',
    observacoes     TEXT,
    criado_por      INT,
    criado_em       DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_extrato_mes (dominio_id, empresa_id, mes_referencia)
);

-- Itens do extrato (snapshot dos dados no momento da emissão)
CREATE TABLE extrato_itens (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    extrato_id      INT NOT NULL,
    tipo            ENUM('funcionario','placa','extra') NOT NULL,
    descricao       VARCHAR(200) NOT NULL,
    quantidade      INT DEFAULT 1,
    valor_unitario  DECIMAL(10,2) NOT NULL,
    valor_total     DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (extrato_id) REFERENCES extratos(id) ON DELETE CASCADE
);
```

---

## Backend Node.js + Express

### `server/.env`
```env
PORT=3001
DB_HOST=localhost
DB_USER=seu_usuario
DB_PASSWORD=sua_senha
DB_NAME=techps_extrato
JWT_SECRET=sua_chave_jwt_secreta
```

### `server/src/config/db.js`
```js
import mysql from 'mysql2/promise';
import dotenv from 'dotenv';
dotenv.config();

export const pool = mysql.createPool({
  host:     process.env.DB_HOST,
  user:     process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  database: process.env.DB_NAME,
  waitForConnections: true,
  connectionLimit: 10,
});
```

### `server/src/routes/auth.js`
```js
import { Router } from 'express';
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { pool } from '../config/db.js';

const router = Router();

// POST /api/auth/login
router.post('/login', async (req, res) => {
  const { email, senha } = req.body;
  if (!email || !senha)
    return res.status(400).json({ erro: 'Email e senha obrigatórios.' });

  const [rows] = await pool.query(
    'SELECT * FROM usuarios WHERE email = ? AND status = "ativo" LIMIT 1',
    [email]
  );
  const user = rows[0];
  if (!user || !(await bcrypt.compare(senha, user.senha_hash)))
    return res.status(401).json({ erro: 'Credenciais inválidas.' });

  const token = jwt.sign(
    { id: user.id, nome: user.nome, nivel: user.nivel },
    process.env.JWT_SECRET,
    { expiresIn: '8h' }
  );

  res.json({ token, usuario: { id: user.id, nome: user.nome, nivel: user.nivel } });
});

export default router;
```

### `server/src/middleware/auth.js`
```js
import jwt from 'jsonwebtoken';

export function autenticar(req, res, next) {
  const header = req.headers.authorization;
  if (!header?.startsWith('Bearer '))
    return res.status(401).json({ erro: 'Token não informado.' });

  try {
    req.usuario = jwt.verify(header.split(' ')[1], process.env.JWT_SECRET);
    next();
  } catch {
    res.status(401).json({ erro: 'Token inválido ou expirado.' });
  }
}
```

### `server/src/routes/cobranca.js` (resumo)
```js
import { Router } from 'express';
import { autenticar } from '../middleware/auth.js';
import { pool } from '../config/db.js';

const router = Router();
router.use(autenticar);

// GET /api/cobranca/valores-ocupacao?dominio_id=1&empresa_id=2
router.get('/valores-ocupacao', async (req, res) => {
  const { dominio_id, empresa_id } = req.query;
  const [rows] = await pool.query(
    'SELECT * FROM valores_ocupacao WHERE dominio_id = ? AND empresa_id = ?',
    [dominio_id, empresa_id]
  );
  res.json(rows);
});

// POST /api/cobranca/valores-ocupacao  (upsert)
router.post('/valores-ocupacao', async (req, res) => {
  const { dominio_id, empresa_id, empresa_cnpj, ocupacao, valor_unitario } = req.body;
  await pool.query(
    `INSERT INTO valores_ocupacao (dominio_id, empresa_id, empresa_cnpj, ocupacao, valor_unitario)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE valor_unitario = VALUES(valor_unitario)`,
    [dominio_id, empresa_id, empresa_cnpj, ocupacao, valor_unitario]
  );
  res.json({ ok: true });
});

// Rotas similares para valores_placa e produtos_cobranca...

export default router;
```

---

## Frontend React + Vite

### `client/.env`
```env
VITE_API_BACKEND=http://localhost:3001/api
```

### `client/src/api/techps.js`
```js
import axios from 'axios';

// Cria instância dinâmica por domínio
export function criarClienteTechPS(urlBase, apiKey) {
  return axios.create({
    baseURL: urlBase,
    headers: { 'X-Api-Key': apiKey },
  });
}

export async function getEmpresas(client) {
  const { data } = await client.get('', { params: { recurso: 'empresas' } });
  return data.empresas;
}

export async function getOverview(client, empresaId) {
  const { data } = await client.get('', { params: { recurso: 'overview', empresa_id: empresaId } });
  return data;
}

export async function getFuncionarios(client, empresaId) {
  const { data } = await client.get('', { params: { recurso: 'funcionarios', empresa_id: empresaId } });
  return data.funcionarios;
}

export async function getPlacas(client, empresaId) {
  const { data } = await client.get('', { params: { recurso: 'placas', empresa_id: empresaId } });
  return data.placas;
}
```

### `client/src/store/authStore.js`
```js
import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export const useAuthStore = create(
  persist(
    (set) => ({
      token: null,
      usuario: null,
      login: (token, usuario) => set({ token, usuario }),
      logout: () => set({ token: null, usuario: null }),
    }),
    { name: 'auth' }
  )
);
```

### `client/src/pages/Login.jsx` (estrutura)
```jsx
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { useAuthStore } from '../store/authStore';
import api from '../api/backend';

const schema = z.object({
  email: z.string().email('E-mail inválido'),
  senha: z.string().min(6, 'Mínimo 6 caracteres'),
});

export default function Login() {
  const { register, handleSubmit, formState: { errors } } = useForm({ resolver: zodResolver(schema) });
  const login = useAuthStore(s => s.login);

  const onSubmit = async (data) => {
    const res = await api.post('/auth/login', data);
    login(res.data.token, res.data.usuario);
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <form onSubmit={handleSubmit(onSubmit)} className="bg-white p-8 rounded-xl shadow w-full max-w-sm space-y-4">
        <h1 className="text-2xl font-bold text-gray-800">TechPS Extrato</h1>
        <input {...register('email')} placeholder="E-mail" className="input" />
        {errors.email && <p className="text-red-500 text-xs">{errors.email.message}</p>}
        <input {...register('senha')} type="password" placeholder="Senha" className="input" />
        {errors.senha && <p className="text-red-500 text-xs">{errors.senha.message}</p>}
        <button type="submit" className="btn-primary w-full">Entrar</button>
      </form>
    </div>
  );
}
```

### `client/src/pages/Dashboard.jsx` (estrutura)
```jsx
// Cards: Total de empresas, Total de funcionários, Total de placas, Valor estimado total
// Gráfico de barras: valor por empresa (Recharts)
// Tabela resumo: empresa | funcionários | placas | valor estimado | status último extrato
```

### `client/src/pages/CadastroCobranca.jsx` (fluxo)
```
1. Selecionar domínio (API TechPS)
2. Selecionar empresa (carrega via API)
3. Carregar ocupações únicas dos funcionários da empresa
4. Para cada ocupação → input de valor unitário
5. Para cada placa → input de valor unitário
6. Adicionar produtos extras (descrição + valor + quantidade)
7. Preview do total estimado
8. Salvar configuração
```

---

## Deploy no cPanel (Node.js App)

### 1. Build do frontend
```bash
cd client
npm run build
# Gera client/dist/
```

### 2. Configurar o servidor para servir o build
No `server/app.js`, sirva o `dist` do React:
```js
import express from 'express';
import path from 'path';
import { fileURLToPath } from 'url';
import authRoutes from './src/routes/auth.js';
import cobrancaRoutes from './src/routes/cobranca.js';
import extratoRoutes from './src/routes/extrato.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const app = express();

app.use(express.json());
app.use('/api/auth',     authRoutes);
app.use('/api/cobranca', cobrancaRoutes);
app.use('/api/extrato',  extratoRoutes);

// Serve o React em produção
app.use(express.static(path.join(__dirname, '../client/dist')));
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, '../client/dist/index.html'));
});

const PORT = process.env.PORT || 3001;
app.listen(PORT, () => console.log(`Servidor rodando na porta ${PORT}`));
```

### 3. No cPanel — Setup Node.js App
```
Application root:  techps-extrato/server
Application URL:   https://seudominio.com.br/extrato
Application startup file: app.js
Node.js version:   18.x ou 20.x
```

### 4. Variáveis de ambiente no cPanel
Adicione no painel "Environment Variables":
```
PORT=3001
DB_HOST=localhost
DB_USER=usuario_mysql
DB_PASSWORD=senha_mysql
DB_NAME=techps_extrato
JWT_SECRET=chave_jwt_longa_e_aleatoria
```

### 5. Instalar dependências e iniciar
No terminal SSH ou pelo cPanel:
```bash
cd techps-extrato/server
npm install
# cPanel inicia automaticamente via Node.js App Manager
```

---

## Dependências

### Backend (`server/package.json`)
```json
{
  "type": "module",
  "dependencies": {
    "express": "^4.18.2",
    "mysql2": "^3.6.0",
    "bcryptjs": "^2.4.3",
    "jsonwebtoken": "^9.0.2",
    "dotenv": "^16.3.1",
    "cors": "^2.8.5"
  }
}
```

### Frontend (`client/package.json`)
```json
{
  "dependencies": {
    "react": "^18.2.0",
    "react-dom": "^18.2.0",
    "react-router-dom": "^6.20.0",
    "axios": "^1.6.0",
    "zustand": "^4.4.0",
    "react-hook-form": "^7.48.0",
    "@hookform/resolvers": "^3.3.0",
    "zod": "^3.22.0",
    "recharts": "^2.9.0",
    "@tanstack/react-table": "^8.10.0"
  },
  "devDependencies": {
    "vite": "^5.0.0",
    "@vitejs/plugin-react": "^4.2.0",
    "tailwindcss": "^3.3.0",
    "autoprefixer": "^10.4.0",
    "postcss": "^8.4.0"
  }
}
```

---

## Fluxo Completo de Cobrança

```
Selecionar domínio API
        ↓
Selecionar empresa (via GET /empresas)
        ↓
Carregar overview (GET /overview?empresa_id=X)
        ↓
┌─────────────────────────────────────┐
│  Configurar valores de cobrança     │
│  • Por ocupação (Motorista R$ X)    │
│  • Por placa    (ABC-1234 R$ Y)     │
│  • Produtos extras (Suporte R$ Z)   │
└─────────────────────────────────────┘
        ↓
Preview do extrato com totais
        ↓
Emitir extrato (salva snapshot no MySQL)
        ↓
Exportar PDF / Enviar por e-mail
```

---

## Próximos Endpoints a Adicionar na API TechPS

Para enriquecer o extrato no futuro:

| Endpoint | Descrição |
|---|---|
| `?recurso=endossos&empresa_id=X&mes=2026-04` | Horas extras do mês |
| `?recurso=absenteismo&empresa_id=X&mes=2026-04` | Faltas e abonos |
| `?recurso=espelho&funcionario_id=X&mes=2026-04` | Espelho individual |

---

## Segurança

- Senhas armazenadas com `bcryptjs` (hash + salt)
- Autenticação via JWT com expiração de 8h
- API TechPS acessada apenas pelo backend (chave nunca exposta ao frontend)
- CORS configurado para aceitar apenas o domínio do app
- Todas as queries com prepared statements (`mysql2`)
