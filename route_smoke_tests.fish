#!/usr/bin/env fish

set -g BASE "http://studyboard.local"
set -g API "$BASE/api"

# ===== AJUSTE AQUI =====
set -g ADMIN_EMAIL "meunome40@exemplo.com"
set -g ADMIN_PASS  "SenhaForte@2026"

# use um usuário comum válido
set -g USER_EMAIL  "andrea@example.com"
set -g USER_PASS   "Senha123!"

# usado para criar uma tarefa de teste
set -g TEST_DUE_DATE "2026-05-20"
set -g TEST_TITLE "Teste final de rota"
set -g TEST_DESC  "Validando gateway"
# =======================

function section
    echo
    echo "=================================================="
    echo $argv
    echo "=================================================="
end

function request
    set -l label  $argv[1]
    set -l method $argv[2]
    set -l url    $argv[3]
    set -l token  $argv[4]
    set -l body   $argv[5]

    set -l bodyfile (mktemp)
    set -l headerfile (mktemp)

    set -l args -sS -D $headerfile -o $bodyfile -X $method $url

    if test -n "$token"
        set args $args -H "Authorization: Bearer $token"
    end

    if test -n "$body"
        set args $args -H "Content-Type: application/json" --data "$body"
    end

    curl $args
    set -l curl_exit $status
    set -l http_code (awk 'toupper($1) ~ /^HTTP\// {code=$2} END{print code}' $headerfile)
    set -l response (cat $bodyfile)

    section $label
    echo "METHOD: $method"
    echo "URL:    $url"
    if test -n "$token"
        echo "AUTH:   Bearer [token enviado]"
    else
        echo "AUTH:   [sem token]"
    end
    if test -n "$body"
        echo "BODY:   $body"
    end
    echo "CURL_EXIT: $curl_exit"
    echo "HTTP_STATUS: $http_code"
    echo "RESP:   $response"

    set -g LAST_HTTP_CODE $http_code
    set -g LAST_BODY $response

    rm -f $bodyfile $headerfile
end

function head_request
    set -l label $argv[1]
    set -l url   $argv[2]

    section $label
    echo "METHOD: HEAD"
    echo "URL:    $url"
    curl -sS -I $url
end

function login_and_extract_token
    set -l email $argv[1]
    set -l pass  $argv[2]

    curl -s -X POST "$API/auth/login" \
      -H "Content-Type: application/json" \
      -d "{\"email\":\"$email\",\"password\":\"$pass\"}" \
      | php -r '$j=json_decode(stream_get_contents(STDIN), true); echo $j["data"]["access_token"] ?? "";'
end

function extract_task_id
    php -r '$j=json_decode(stream_get_contents(STDIN), true); echo $j["data"]["id"] ?? "";'
end

section "LOGIN E TOKENS"

set -g ADMIN_TOKEN (login_and_extract_token $ADMIN_EMAIL $ADMIN_PASS)
echo "ADMIN_EMAIL: $ADMIN_EMAIL"
echo "ADMIN_TOKEN: $ADMIN_TOKEN"

set -g USER_TOKEN (login_and_extract_token $USER_EMAIL $USER_PASS)
echo "USER_EMAIL: $USER_EMAIL"
echo "USER_TOKEN: $USER_TOKEN"

if test -z "$ADMIN_TOKEN"
    echo
    echo "ERRO: não foi possível obter ADMIN_TOKEN."
    echo "Revise ADMIN_EMAIL e ADMIN_PASS no topo do script."
    exit 1
end

if test -z "$USER_TOKEN"
    echo
    echo "AVISO: não foi possível obter USER_TOKEN."
    echo "Os testes de usuário comum podem falhar. Revise USER_EMAIL e USER_PASS."
end

section "1. SAÚDE"

request "Gateway health" GET "$API/health" "" ""
request "Gateway ready"  GET "$API/ready"  "" ""

section "2. AUTENTICAÇÃO"

request "Perfil sem token" GET "$API/auth/me" "" ""
request "Perfil com token admin" GET "$API/auth/me" "$ADMIN_TOKEN" ""

request "Login admin com senha atual" POST "$API/auth/login" "" "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASS\"}"

section "3. TAREFAS"

request "Criar tarefa" POST "$API/tasks" "$ADMIN_TOKEN" "{\"title\":\"$TEST_TITLE\",\"description\":\"$TEST_DESC\",\"priority\":\"alta\",\"status\":\"pendente\",\"due_date\":\"$TEST_DUE_DATE\"}"
set -g CREATED_TASK_ID (echo $LAST_BODY | extract_task_id)
echo "CREATED_TASK_ID: $CREATED_TASK_ID"

request "Listar tarefas" GET "$API/tasks" "$ADMIN_TOKEN" ""
request "Filtrar tarefas por status" GET "$API/tasks?status=pendente" "$ADMIN_TOKEN" ""
request "Filtrar tarefas por prioridade" GET "$API/tasks?priority=alta" "$ADMIN_TOKEN" ""
request "Filtrar tarefas por prazo" GET "$API/tasks?due_date=$TEST_DUE_DATE" "$ADMIN_TOKEN" ""
request "Filtro de prazo inválido" GET "$API/tasks?due_date=20-05-2026" "$ADMIN_TOKEN" ""

request "Consultar tarefa inexistente" GET "$API/tasks/999999" "$ADMIN_TOKEN" ""

if test -n "$CREATED_TASK_ID"
    request "Consultar tarefa criada" GET "$API/tasks/$CREATED_TASK_ID" "$ADMIN_TOKEN" ""
    request "Atualizar tarefa criada" PUT "$API/tasks/$CREATED_TASK_ID" "$ADMIN_TOKEN" "{\"status\":\"em_andamento\"}"
    request "Excluir tarefa criada" DELETE "$API/tasks/$CREATED_TASK_ID" "$ADMIN_TOKEN" ""
else
    section "Consultar/Atualizar/Excluir tarefa criada"
    echo "PULADO: não foi possível capturar o ID da tarefa criada."
end

section "4. ADMIN"

request "Admin lista usuários" GET "$API/admin/users" "$ADMIN_TOKEN" ""
request "Admin/users sem token" GET "$API/admin/users" "" ""

if test -n "$USER_TOKEN"
    request "Usuário comum tenta listar usuários" GET "$API/admin/users" "$USER_TOKEN" ""
else
    section "Usuário comum tenta listar usuários"
    echo "PULADO: USER_TOKEN vazio."
end

section "5. LOGOUT"

set -g LOGOUT_TOKEN (login_and_extract_token $ADMIN_EMAIL $ADMIN_PASS)
echo "LOGOUT_TOKEN: $LOGOUT_TOKEN"

if test -n "$LOGOUT_TOKEN"
    request "Logout" POST "$API/auth/logout" "$LOGOUT_TOKEN" ""
    request "Reuso do token após logout" GET "$API/auth/me" "$LOGOUT_TOKEN" ""
else
    section "Logout"
    echo "PULADO: não foi possível obter LOGOUT_TOKEN."
end

section "6. MÉTODOS INVÁLIDOS"

request "GET em /auth/login" GET "$API/auth/login" "" ""
request "PUT em /auth/logout" PUT "$API/auth/logout" "$ADMIN_TOKEN" ""
request "PATCH em /tasks/1" PATCH "$API/tasks/1" "$ADMIN_TOKEN" ""

section "7. ROTAS WEB AMIGÁVEIS"

head_request "Página inicial" "$BASE/"
head_request "Página login" "$BASE/login"
head_request "Página cadastro" "$BASE/cadastro"
head_request "Página tarefas" "$BASE/tarefas"
head_request "Página perfil" "$BASE/perfil"
head_request "Página usuários" "$BASE/usuarios"

section "8. ROTA INEXISTENTE"

request "API rota inexistente" GET "$API/rota-inexistente" "" ""

section "FIM"
echo "Testes concluídos."
