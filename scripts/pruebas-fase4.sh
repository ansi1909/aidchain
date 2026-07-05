#!/usr/bin/env bash
#
# Pruebas de API para la Fase 4 (firma cruzada + validaciones de rol).
#
# Cubre los casos negativos que NO requieren una firma ECDSA válida (el camino
# feliz se prueba por la UI, donde el navegador firma con la llave privada).
#
# Uso:
#   bash scripts/pruebas-fase4.sh
#   BASE_URL=http://127.0.0.1:8000 ENCARGADO_ID=2 LOTE_REAL=LOTE-20260702-AB12 \
#     bash scripts/pruebas-fase4.sh
#
# Variables opcionales:
#   BASE_URL       URL del backend (default: http://127.0.0.1:8000)
#   ENCARGADO_ID   coordinatorId con rol encargado_refugio (habilita el caso 4.5)
#   LOTE_REAL      loteId real EN_TRANSITO (habilita el caso 4.5)

set -u

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
PASS=0
FAIL=0

# Ejecuta una petición y compara el código HTTP con el esperado.
#   $1 = descripción, $2 = método, $3 = ruta, $4 = esperado(s, separados por |), $5 = body(json|"")
check() {
  local desc="$1" method="$2" path="$3" expected="$4" body="${5:-}"
  local code

  if [ -n "$body" ]; then
    code=$(curl -s -o /dev/null -w '%{http_code}' -X "$method" "$BASE_URL$path" \
      -H 'Content-Type: application/json' -d "$body")
  else
    code=$(curl -s -o /dev/null -w '%{http_code}' -X "$method" "$BASE_URL$path")
  fi

  if echo "|$expected|" | grep -q "|$code|"; then
    echo "  ✔ [$code] $desc"
    PASS=$((PASS + 1))
  else
    echo "  ✘ [$code, esperaba $expected] $desc"
    FAIL=$((FAIL + 1))
  fi
}

echo "== Pruebas API Fase 4 =="
echo "Backend: $BASE_URL"
echo

echo "-- Salud / cadena --"
check "GET /api/ledger/verify responde (200 íntegra / 409 con rupturas)" \
  GET "/api/ledger/verify" "200|409"

echo
echo "-- Listado de pendientes --"
check "GET /api/ledger/dispatches/pending responde 200" \
  GET "/api/ledger/dispatches/pending" "200"

echo
echo "-- Registro: validación de roles --"
check "register sin roles => 400" \
  POST "/api/coordinators/register" "400" \
  '{"nombre":"Sin Rol","publicKey":"x","organizationId":1}'

check "register con rol inválido => 400" \
  POST "/api/coordinators/register" "400" \
  '{"nombre":"Rol Malo","roles":["superadmin"],"publicKey":"x","organizationId":1}'

echo
echo "-- Recepción: validaciones de negocio --"
check "receive sin firmaDestino => 400" \
  POST "/api/ledger/dispatches/LOTE-X/receive" "400" \
  '{"coordinatorId":1}'

check "receive con coordinatorId inexistente => 400" \
  POST "/api/ledger/dispatches/LOTE-X/receive" "400" \
  '{"coordinatorId":999999,"firmaDestino":"x"}'

# 4.4 — Lote inexistente (requiere un encargado válido para pasar el check de rol).
if [ -n "${ENCARGADO_ID:-}" ]; then
  check "receive de lote inexistente con encargado válido => 404" \
    POST "/api/ledger/dispatches/LOTE-NO-EXISTE-9999/receive" "404" \
    "{\"coordinatorId\":$ENCARGADO_ID,\"firmaDestino\":\"x\"}"
else
  echo "  … (4.4 omitido: exporta ENCARGADO_ID para probar el 404 real)"
fi

# 4.5 — Firma inválida sobre un lote real EN_TRANSITO.
if [ -n "${ENCARGADO_ID:-}" ] && [ -n "${LOTE_REAL:-}" ]; then
  check "receive con firma inválida sobre lote real => 422" \
    POST "/api/ledger/dispatches/$LOTE_REAL/receive" "422" \
    "{\"coordinatorId\":$ENCARGADO_ID,\"firmaDestino\":\"firma-invalida\"}"
else
  echo "  … (4.5 omitido: exporta ENCARGADO_ID y LOTE_REAL para probar el 422)"
fi

echo
echo "== Resultado: $PASS ✔ / $FAIL ✘ =="
[ "$FAIL" -eq 0 ]
