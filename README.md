# AidChain — Trazabilidad humanitaria con ledger criptográfico

AidChain es una plataforma para la **trazabilidad de ayuda humanitaria** que garantiza la
integridad de la cadena de suministro mediante un **ledger criptográfico inmutable**. Cada
evento de inventario (despacho, recepción, entrega a beneficiario) se **firma digitalmente**
con la llave privada del coordinador que lo origina y se **encadena por hash** al evento
anterior, de modo que cualquier manipulación posterior de la base de datos es detectable.

El objetivo es aportar **transparencia y no repudio** en operaciones de terreno: saber
quién despachó qué, quién lo recibió, y que ese registro no fue alterado.

---

## Características principales

- **Ledger criptográfico inmutable**: eventos encadenados por hash con firma ECDSA (P-256).
- **Identidad criptográfica por coordinador**: el par de llaves se genera en el navegador; el
  servidor solo guarda la clave pública para verificar firmas.
- **Flujo de dos etapas**:
  - *Logística* (`OUT_DISPATCH`): un **Despachador** abastece un refugio; el lote viaja con un QR.
  - *Recepción* (firma cruzada): el **Encargado de refugio** confirma la recepción firmando el
    mismo payload, consolidando el evento y actualizando el stock.
  - *Última milla* (`OUT_BENEFICIARY`): el **Encargado de refugio** entrega a beneficiarios
    contra el stock disponible.
- **Control de doble cobro**: validación de umbrales para prevenir entregas duplicadas.
- **Necesidades por refugio**: reporte y priorización de insumos requeridos.
- **Inventario y cobertura**: panel que cruza *Necesidad vs Despachado vs Stock disponible*.
- **Auditoría de cadena**: comando CLI que verifica la integridad completa del ledger.

---

## Stack tecnológico

**Backend**
- PHP 8.2+ · Symfony 7.4
- Doctrine ORM 3 · Doctrine Migrations
- MySQL / MariaDB
- LexikJWTAuthenticationBundle · NelmioCorsBundle

**Frontend**
- Vue 3 · Vite 8
- Pinia (estado) · Vue Router
- TailwindCSS 4
- axios · qrcode · xlsx

---

## Requisitos previos

- PHP >= 8.2 con extensiones `ctype`, `iconv`, `openssl`
- Composer
- Node.js 18+ y npm
- MySQL o MariaDB en ejecución

---

## Puesta en marcha

### 1. Backend (API Symfony)

```bash
# Instalar dependencias PHP
composer install

# Configurar variables de entorno locales (NO commitear secretos)
# Crea/edita .env.local con los valores reales. Los archivos .env, .env.dev y .env.test
# contienen placeholders; los secretos reales deben ir en .env.local (ignorado por Git):
#
#   APP_SECRET=tu_app_secret_real
#   DATABASE_URL="mysql://usuario:clave@127.0.0.1:3306/aidchain?serverVersion=10.11.2-MariaDB&charset=utf8mb4"
#   JWT_PASSPHRASE=tu_passphrase_real
#   GEMINI_API_KEY=tu_clave_api_gemini

# Crear el esquema aplicando las migraciones
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction

# (Opcional) Cargar datos de demostración (organizaciones y refugios)
php bin/console app:seed:demo

# Levantar el servidor de desarrollo
symfony serve
# o, sin Symfony CLI:
php -S localhost:8000 -t public
```

La API queda disponible en `http://localhost:8000`.

### 2. Frontend (SPA Vue)

```bash
cd frontend
npm install

# Servidor de desarrollo (con recarga en caliente)
npm run dev

# Build de producción
npm run build
```

---

## Comandos útiles

```bash
# Verificar la integridad criptográfica de la cadena del ledger
php bin/console app:ledger:audit-chain

# Cargar datos de demo (idempotente)
php bin/console app:seed:demo

# Ejecutar la batería de pruebas
php bin/phpunit
```

---

## Roles y separación de responsabilidades

| Rol | Puede originar |
|-----|----------------|
| `DESPACHADOR` | Despacho logístico (`OUT_DISPATCH`), ingreso a bodega (`IN_STOCK`) |
| `ENCARGADO_REFUGIO` | Confirmación de recepción (firma cruzada), entrega a beneficiario (`OUT_BENEFICIARY`) |
| `AUDITOR` | Lectura, auditoría y liberación de bloqueos |

Un coordinador puede acumular varios roles (multi-rol), habitual en operaciones pequeñas.

---

## Estructura del proyecto

```
aidchain/
├── src/
│   ├── Controller/     # Endpoints de la API (ledger, refugios, stock, necesidades…)
│   ├── Entity/         # Entidades Doctrine (InventoryEvent, Coordinator, ShelterStock…)
│   ├── Repository/     # Repositorios Doctrine
│   ├── Service/        # Lógica de dominio (CryptoLedgerService, ShelterStockService…)
│   ├── Enum/           # Enumeraciones (EventType, CoordinatorRole…)
│   └── Command/        # Comandos CLI (auditoría de cadena, seed de demo)
├── migrations/         # Migraciones de base de datos
├── config/             # Configuración de Symfony y bundles
├── public/             # Punto de entrada HTTP (index.php)
├── tests/              # Pruebas PHPUnit
└── frontend/           # SPA Vue 3 + Vite
    └── src/
        ├── views/      # Vistas (Despacho, Recepción, Inventario, Necesidades…)
        ├── stores/     # Stores Pinia
        ├── services/   # Cliente API y utilidades criptográficas
        └── components/ # Componentes de UI reutilizables
```

---

## Nota de seguridad

- **No commitees secretos**. Define credenciales, claves JWT y API keys en `.env.local`
  (ignorado por Git), nunca en archivos versionados.
- Las llaves JWT (`config/jwt/*.pem`) y los `.env.local` están excluidos vía `.gitignore`.
- La identidad criptográfica de cada coordinador se genera y custodia en su navegador; el
  servidor solo almacena la **clave pública**.

---

## Licencia

Proyecto propietario. Todos los derechos reservados.
