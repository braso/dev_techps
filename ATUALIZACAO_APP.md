# Atualização automática do app (Android) pelo sistema

Data: 2026-09-26. A partir da versão **1.1.015** o app verifica no servidor da empresa se existe versão nova, baixa o APK e abre o instalador do Android. Não é mais necessário enviar APK aos funcionários.

## Fluxo
1. Admin publica o APK em **Cadastros > Versões do App** (`app_versao.php`): código da versão (versionCode), nome, arquivo APK, notas e se é obrigatória.
2. O arquivo vai para `ws/app/<arquivo>.apk` e o registro para a tabela `app_versao` (criada automaticamente). Só a versão mais nova fica ativa.
3. O app consulta `GET /ws/app/version` ao abrir, ao entrar e ao voltar do segundo plano (no máximo a cada 15 min). Resposta:
   `{available, versionCode, versionName, url, size, sha256, notes, mandatory, publishedAt}`.
4. Se `versionCode` publicado > instalado: aviso com as notas. "Atualizar" baixa o APK (barra de progresso, conferência SHA-256) e abre o instalador. "Agora não" pula aquela versão (não vale para obrigatória).
5. Na primeira vez o Android pede a permissão "instalar apps desconhecidos" para o TechPS; o app abre a tela certa e orienta.

## Regras importantes
- **versionCode sempre maior** que o anterior. É esse número (em `android/app/build.gradle`) que o Android usa para aceitar a atualização. A tela recusa código igual ou menor.
- **Mesma assinatura**: o APK novo precisa ser assinado com a mesma chave das versões instaladas (hoje o build de release usa `android/app/debug.keystore` do projeto; manter esse arquivo).
- O nome exibido no rodapé do login agora vem do próprio pacote (`versionName`), não precisa editar texto.
- Limite de upload do PHP (`upload_max_filesize`/`post_max_size`) precisa comportar o APK (~23 MB). A tela mostra o limite atual.
- A pasta `ws/app/` precisa de permissão de escrita para o PHP.

## Arquivos
- Sistema: `app_versao.php` (tela), `menu_estrutura.php` (menu), `ws/endpoints.php` (`get_app_version`), `ws/index.php` (rota `app`), `ws/.htaccess` (MIME .apk), `ws/app/index.php` (encaminha a rota no servidor embutido), `ws/app/.gitignore`.
- App: `android/.../updater/UpdaterModule.java` + `UpdaterPackage.java`, `AndroidManifest.xml` (permissão + FileProvider), `res/xml/file_paths.xml`, `src/utils/updater.ts`, `src/components/UpdateChecker`, `App.tsx`, `build.gradle` (versionCode 15 / 1.1.015).

## Para publicar uma nova versão do app
1. No projeto do app, aumentar `versionCode` e `versionName` em `android/app/build.gradle`.
2. `cd android && ./gradlew.bat assembleRelease` (por empresa, com o tenant certo em `settings.ts`).
3. Em cada empresa: Cadastros > Versões do App > enviar o APK com o mesmo código/nome.
