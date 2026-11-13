# PHP Git Deploy

Atualize projetos hospedados a partir do GitHub por uma URL HTTPS. O script clona uma branch na primeira execução, atualiza clones existentes nas próximas e pode executar comandos como `composer install`. A página mostra o resultado de cada comando e pode enviar o log ao Telegram.

## 1. Prepare a hospedagem

- Use uma hospedagem Linux com PHP, Git disponível no `PATH` do PHP e saída HTTPS para `github.com`. O PHP precisa permitir `exec`, `putenv` e `set_time_limit`. Para Telegram, habilite também a extensão cURL e o acesso a `api.telegram.org`.
- O usuário que executa o PHP precisa conseguir escrever no diretório de destino e nos arquivos do clone. Para clonar, o diretório pai precisa existir e permitir a criação da pasta final.
- No cPanel, crie uma pasta para o deploy, por exemplo `public_html/git-deploy`, e envie `index.php`, `telegram.php`, `.htaccess` e `deploy.json.example` da branch `main`. No Gerenciador de Arquivos, habilite **Mostrar arquivos ocultos** para conferir o `.htaccess`.
- Ative HTTPS e proteja a pasta com **Privacidade do Diretório** no cPanel, ou use um controle de acesso equivalente na hospedagem. Qualquer pessoa que consiga chamar o endpoint pode disparar o deploy. O script não autentica chamadas nem valida assinaturas de webhooks.
- Copie `deploy.json.example` para `deploy.json` na mesma pasta do `index.php`. Confirme que o servidor aplica o `.htaccess`: ao abrir `https://seu-dominio.com.br/git-deploy/deploy.json`, inclusive após autenticar na pasta, o conteúdo deve estar indisponível. Em servidores que ignoram `.htaccess`, bloqueie esse caminho na configuração do servidor antes de gravar credenciais.

## 2. Crie o token do GitHub

Para um repositório público, omita o campo `token`. Para um repositório privado:

1. Abra [New fine-grained personal access token](https://github.com/settings/personal-access-tokens/new) no GitHub.
2. Dê um nome ao token, por exemplo `Deploy cPanel`, e defina a validade.
3. Em **Resource owner**, escolha a conta ou organização dona do repositório de destino.
4. Em **Repository access**, escolha **Only select repositories** e selecione os repositórios privados que serão baixados. Por exemplo, para publicar `maycorolbuche/mdawiki-site`, selecione `mdawiki-site`.
5. Em **Permissions → Repository permissions**, selecione **Contents → Read-only**. O GitHub inclui **Metadata → Read-only** automaticamente. As operações padrão de clone e fetch usam apenas leitura.
6. Clique em **Generate token** e copie o valor gerado para o campo `token` da entrada correspondente no `deploy.json`. Se a organização exigir aprovação, aguarde a liberação do token para acessar o repositório privado.

Guarde o token no servidor e mantenha `deploy.json` fora do Git. Comandos personalizados que acessam outros serviços ou repositórios precisam das próprias credenciais.

[Documentação do GitHub sobre tokens de acesso pessoal](https://docs.github.com/pt/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens)

## 3. Configure o `deploy.json`

O arquivo deve conter uma lista JSON com uma entrada por projeto. Exemplo para um repositório privado:

```json
[
    {
        "remote_repository": "maycorolbuche/mdawiki-site",
        "local_dir": "/home/SEU_USUARIO/sites/mda.wiki.br/site",
        "branch": "main",
        "token": "COLE_SEU_TOKEN_AQUI"
    }
]
```

| Campo | Como preencher |
| --- | --- |
| `remote_repository` | Dono e nome no formato `dono/repositorio`, sem a URL do GitHub. |
| `local_dir` | Caminho no servidor. Prefira o caminho absoluto para evitar ambiguidades. Se o diretório final ainda não existir, o script faz o clone. Se existir, deve ser um clone Git com o remoto `origin` e a branch de acompanhamento correspondente. Uma string vazia usa o diretório de trabalho do PHP. |
| `branch` | Nome exato da branch, como `main`. Em clones existentes, deixe a branch configurada já em uso no diretório. |
| `token` | Token do GitHub para repositórios privados. Pode ser omitido para repositórios públicos. |
| `commands` | Lista opcional de comandos de shell executados após o clone ou a atualização, no diretório do projeto. Exemplo: `["composer install --no-dev"]`. Os executáveis usados precisam estar disponíveis para o PHP. |
| `telegram` | Lista opcional de destinos. Cada destino tem `bot_token` e `chat_id`. |

Para receber o log no Telegram, crie um bot com o **@BotFather**, inicie uma conversa com ele ou adicione-o ao grupo de destino com permissão para enviar mensagens. Inclua, na entrada do projeto, um campo como `"telegram": [{"bot_token": "TOKEN_DO_BOT", "chat_id": "-1001234567890"}]`. O envio ocorre em chamadas que informam `remote`.

## 4. Execute o primeiro deploy

1. Abra `https://seu-dominio.com.br/git-deploy/index.php?status=true`. A página deve mostrar `Ok` sem executar o Git. Esse teste verifica o endpoint, não a credencial nem o acesso ao repositório.
2. Abra `https://seu-dominio.com.br/git-deploy/index.php?remote=maycorolbuche/mdawiki-site`, substituindo o valor de `remote` pelo mesmo texto do campo `remote_repository`.
3. Confira o log e verifique os arquivos no diretório de destino. Se uma etapa falhar, os comandos seguintes daquela entrada são interrompidos.

| Parâmetro | Efeito |
| --- | --- |
| `status=true` | Retorna `Ok` sem executar deploy. |
| `remote=dono/repositorio` | Executa somente as entradas com esse `remote_repository`. Sem `remote`, executa todas as entradas. |
| `force=true` | Acrescenta `--force` ao `git fetch` em clones existentes. A sobrescrita de arquivos ocorre também sem esse parâmetro. |

Em clones existentes, o script baixa a branch com `git fetch` e aplica o commit baixado com `git reset --hard FETCH_HEAD`. O reset descarta alterações em arquivos rastreados e sobrescreve arquivos locais que ocupem caminhos da versão remota. Arquivos locais fora desses caminhos, como um `.env` mantido fora do Git, são preservados. Se o fetch falhar, o script interrompe o deploy antes do reset. Use um clone dedicado ao deploy e mantenha dados de produção e configurações locais fora dos caminhos versionados.

### Agendamento

Para rodar pelo cron do cPanel, use um cliente HTTP como `curl` para chamar a URL com `remote`. Se a pasta estiver protegida por autenticação HTTP, mantenha as credenciais em um arquivo fora de `public_html`, legível apenas pelo usuário da hospedagem, e passe esse arquivo com `curl --netrc-file`.

O endpoint atual não interpreta a carga útil de webhooks do GitHub. Para automatizar por push, faça o workflow do repositório chamar a URL com `remote` após os checks da branch publicada, guardando as credenciais HTTP em **GitHub Actions secrets**. O token de leitura do GitHub continua no `deploy.json` do servidor.

## 5. Atualize uma instalação existente ou troque o token

1. No cPanel, atualize `index.php`, `telegram.php` e `.htaccess` a partir da branch `main`. Preserve o `deploy.json` do servidor.
2. Em `deploy.json`, atualize o campo `token` de cada repositório privado que usa a credencial antiga.
3. Execute a URL com `remote` para validar um projeto por vez. O script ajusta o `origin` para uma URL HTTPS sem credenciais e fornece o token atual ao Git durante a operação.
4. Revogue qualquer token antigo que tenha aparecido em logs da página ou em mensagens do Telegram.

## Solução de problemas

| Sintoma | O que conferir |
| --- | --- |
| `could not read Password` com `TOKEN@github.com` | A instalação antiga usava o token como nome de usuário. Atualize o script e informe o token válido no `deploy.json`. |
| `Authentication failed`, `403` ou `Repository not found` | Confira o valor de `remote_repository`, a validade do token, **Contents → Read-only**, a seleção do repositório e eventual aprovação pela organização. |
| `not a git repository` ou `No such remote 'origin'` | Confira se `local_dir` aponta para um clone existente com `origin`. Para clonar do zero, use um destino que ainda não exista. |
| `git: not found`, erro de `exec()` ou `Permission denied` | Confira se o Git está no `PATH` do PHP, se a hospedagem permite as funções necessárias e se o usuário do PHP tem acesso de escrita ao destino. |
| O filtro `remote` não executa nenhum comando | Confira se o valor na URL é idêntico ao `remote_repository` cadastrado. |
| O deploy executa, mas o Telegram não recebe | Use uma URL com `remote` e confira a extensão cURL, o acesso a `api.telegram.org`, o token do bot, o ID do chat e a permissão de envio. |
