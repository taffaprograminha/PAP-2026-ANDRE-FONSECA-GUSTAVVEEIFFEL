# PAP 2026 — Sistema de Controlo de Acessos RFID

Projeto da Prova de Aptidão Profissional (PAP) desenvolvido por **André Fonseca** na escola **Gustave Eiffel**.

Sistema web de gestão e monitorização de acessos por cartões RFID, com integração Arduino e painel de administração completo.

---

## Funcionalidades

- **Dashboard em tempo real** — estatísticas de acessos (hoje, 7 dias, 30 dias), gráficos de distribuição e atividade recente
- **Gestão de utilizadores** — registar, ativar/desativar e bloquear cartões RFID
- **Gestão de placas Arduino** — monitorizar estado (online/offline), registar novas placas com API key própria
- **Leituras RFID** — histórico completo de acessos com filtros por período, utilizador e placa
- **Estatísticas avançadas** — gráficos de acessos permitidos vs negados, exportação para Excel
- **Chatbot com IA** — assistente integrado com LLM (Groq/LLaMA) para consultas sobre o sistema
- **Autenticação segura** — login com rate limiting, 2FA, sessões protegidas (HTTPOnly, SameSite)
- **Definições do utilizador** — tema escuro, notificações, fuso horário, formato de data
- **Bridge Serial → HTTP** — script Python que lê cartões RFID via porta serial e envia para a API

## Arquitetura

```
┌─────────────┐     Serial      ┌──────────────┐     HTTP/POST     ┌──────────────┐
│   Arduino    │ ──────────────► │ rfid_bridge  │ ────────────────► │   API PHP    │
│  + Leitor    │                 │   (Python)   │                   │  (api_rfid)  │
│    RFID      │ ◄────────────── │              │                   │              │
└─────────────┘   Resposta       └──────────────┘                   └──────┬───────┘
                  (ACESSO_OK/                                              │
                   ACESSO_NEG)                                             ▼
                                                                   ┌──────────────┐
                                                                   │    MySQL     │
                                                                   │   (XAMPP)    │
                                                                   └──────┬───────┘
                                                                          │
                                                                          ▼
                                                                   ┌──────────────┐
                                                                   │  Dashboard   │
                                                                   │    Web       │
                                                                   └──────────────┘
```

## Tecnologias

| Camada | Tecnologias |
|--------|-------------|
| Frontend | PHP, HTML, CSS, JavaScript, Chart.js |
| Backend | PHP 8, MySQL |
| Hardware | Arduino, Leitor RFID (MFRC522) |
| Bridge | Python 3 (pyserial, requests) |
| IA | Groq API (LLaMA 4 Scout) |
| Servidor | XAMPP (Apache + MySQL) |

## Instalação

1. **Clonar o repositório** na pasta `htdocs` do XAMPP:
   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs
   git clone https://github.com/taffaprograminha/PAP-2026-ANDRE-FONSECA-GUSTAVVEEIFFEL.git demo
   ```

2. **Criar a base de dados** `ola` no phpMyAdmin e importar as tabelas necessárias:
   - `users` — contas de administrador
   - `users_autorizados` — utilizadores com cartão RFID
   - `placas_arduino` — placas registadas
   - `leituras_rfid` — histórico de leituras
   - `cartoes_bloqueados` — cartões bloqueados
   - `logs_login` — logs de tentativas de login

3. **Configurar o `.htaccess`** na raiz do projeto (não incluído no repositório por segurança):
   ```apache
   SetEnv GROQ_API_KEY "a_tua_chave_aqui"
   ```

4. **Instalar dependências Python** (para o bridge):
   ```bash
   pip install pyserial requests
   ```

5. **Ligar o Arduino** e executar o bridge:
   ```bash
   python3 rfid_bridge.py
   ```

## Estrutura do Projeto

```
├── login.php              # Página de login com rate limiting
├── dashboard.php          # Painel principal com estatísticas
├── users.php              # Gestão de utilizadores RFID
├── placas.php             # Gestão de placas Arduino
├── leituras_rfid.php      # Histórico de leituras
├── estatisticas.php       # Gráficos e análises
├── settings.php           # Definições do utilizador
├── chat_api.php           # API do chatbot com IA
├── api_rfid.php           # API para leituras RFID (Arduino)
├── api_users.php          # API de gestão de utilizadores
├── api_placas.php         # API de gestão de placas
├── rfid_bridge.py         # Bridge serial → HTTP (Python)
├── config.php             # Configurações (API keys via env)
├── ligacao.php            # Conexão à base de dados
└── _sidebar.php           # Navegação lateral partilhada
```

## Autor

**André Fonseca** — Gustave Eiffel, 2026
