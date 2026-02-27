# Deploy no Coolify (Laravel + Inertia)

## Variaveis de ambiente minimas

Defina no service do app:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://SEU_DOMINIO`
- `ASSET_URL=https://SEU_DOMINIO`
- `FORCE_HTTPS=true`
- `TRUSTED_PROXIES=*`
- `SESSION_DRIVER=database`
- `SESSION_DOMAIN=SEU_DOMINIO` (sem `https://`; para subdominios use `.seu-dominio.com`)
- `SESSION_SECURE_COOKIE=true`
- `SESSION_SAME_SITE=lax`

Se frontend e backend ficarem em dominios diferentes:

- `FRONTEND_URL=https://DOMINIO_FRONT`
- `SESSION_SAME_SITE=none`
- `SESSION_SECURE_COOKIE=true`

Para MinIO/S3 (URLs de imagens no frontend):

- `AWS_ACCESS_KEY_ID=...`
- `AWS_SECRET_ACCESS_KEY=...`
- `AWS_DEFAULT_REGION=...`
- `AWS_BUCKET=...`
- `AWS_ENDPOINT=http://minio:9000` (interno, se usar MinIO interno)
- `AWS_USE_PATH_STYLE_ENDPOINT=true` (normalmente para MinIO)
- `AWS_URL=https://ARMAZENAMENTO_PUBLICO/BUCKET` (URL publica consumida pelo navegador)

## Comandos pos-deploy

```bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
```

## Diagnostico rapido

- Login redireciona em loop: revisar `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `APP_URL`, `FORCE_HTTPS`.
- Front nao carrega assets: revisar `APP_URL`, `ASSET_URL`, build do Vite.
- Imagens/upload quebrados: revisar `AWS_URL` (nao usar host interno para navegador).
- Erros de CORS: revisar `FRONTEND_URL` e `config/cors.php`.
