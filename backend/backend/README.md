# Autentica Fashion Backend

API em PHP + MySQL pronta para subir no Git e publicar no Render via Docker.

## Estrutura
- `index.php` -> entrada da API
- `.htaccess` -> rewrite para rotas
- `backend/src` -> controllers, core e services
- `backend/config` -> configurações
- `backend/database/schema.sql` -> schema base
- `backend/database/update_database.sql` -> ajustes de clientes e pedidos
- `backend/.env.example` -> variáveis de ambiente
- `Dockerfile` -> deploy no Render
- `render.yaml` -> serviço sugerido para Render

## Variáveis de ambiente
Copie `backend/.env.example` para `backend/.env` em ambiente local.
No Render, configure estas variáveis no painel do serviço:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://seu-servico.onrender.com`
- `APP_FRONTEND_URLS=https://www.autenticafashionf.store,https://autenticafashionf.store`
- `APP_FRONTEND_URL=https://www.autenticafashionf.store`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `ADMIN_USERNAME`
- `ADMIN_PASSWORD`
- `JWT_SECRET`
- `JWT_EXPIRES_IN`
- `CLOUDINARY_CLOUD_NAME`
- `CLOUDINARY_API_KEY`
- `CLOUDINARY_API_SECRET`
- `CLOUDINARY_UPLOAD_FOLDER`

## Render
### Deploy rápido
1. suba esta pasta para o GitHub
2. no Render, crie um novo **Web Service** a partir do repositório
3. escolha **Docker**
4. em Root Directory, use `backend`
5. adicione as variáveis de ambiente acima
6. aponte o front para a URL do serviço Render

### Banco
Importe:
- `backend/database/schema.sql`
- depois `backend/database/update_database.sql`

## Observações
- CORS aceita os domínios do front definidos em `APP_FRONTEND_URLS`
- conexão PDO está com reuso dentro do processo e persistência ligada
- login de cliente não quebra se a tabela `orders` ainda não tiver `customer_id`
