# Prompts sent to the local Splash coding worker

Each entry: timestamp, the full prompt Claude sent to `splash-delegate`, and a short outcome.

## 2026-11-09 16:26 - Catalog entities (Product, Seller, Offer, ProductKey)

```text
Task: create the catalog Doctrine entities (Product, Seller, Offer, ProductKey + KeyStatus enum) and their migration in the Symfony 7.4 backend.

Context
- Project root: current directory. Backend is in `backend/` (Symfony 7.4, Doctrine ORM 3, PHP 8.5, PostgreSQL 16). There is NO PHP on the host: run every PHP/console command inside docker, e.g. `docker compose exec -T api php bin/console ...` (working dir in container is /app = backend/). Containers are already running; do not stop/restart them or any other containers.
- Doctrine uses attribute mapping in `backend/src/Entity`, repositories in `backend/src/Repository`, underscore naming strategy, identity ids. Existing migration `backend/migrations/Version20261109000000.php` (pg_trgm extension) is already executed; do not edit it.
- Use `declare(strict_types=1);`, typed properties, constructor-initialised required fields, getters (and setters only where noted). Money is integer cents. Use `\DateTimeImmutable` for dates.

Files to create
1. `backend/src/Entity/KeyStatus.php`: `enum KeyStatus: string { case Available = 'available'; case Reserved = 'reserved'; case Sold = 'sold'; }`
2. `backend/src/Entity/Product.php`, table `products`:
   id int identity; slug string(160) unique; name string(200); description text; coverUrl string(500) nullable; platform string(40) (e.g. "PC", "PlayStation 5"); region string(40) (e.g. "Global", "Europe"); genres json (list of strings, default []); releaseDate date_immutable nullable; igdbId int nullable unique; createdAt datetime_immutable (set in constructor).
   Relation: OneToMany `offers` -> Offer (mappedBy product), as a Doctrine Collection initialised in the constructor, with a getter.
   Setters for description, coverUrl, genres, releaseDate, igdbId.
3. `backend/src/Entity/Seller.php`, table `sellers`: id; name string(120); slug string(120) unique; rating float (0-5, default 5.0); createdAt datetime_immutable.
4. `backend/src/Entity/Offer.php`, table `offers`: id; product ManyToOne Product (not null, onDelete CASCADE, inversedBy offers); seller ManyToOne Seller (not null, onDelete CASCADE); priceCents int; originalPriceCents int nullable (price before discount); currency string(3) default 'EUR'; active bool default true; createdAt datetime_immutable.
   Unique constraint `uniq_offer_product_seller` on (product_id, seller_id). Index `idx_offer_product_active` on (product_id, active).
   Setters for priceCents, originalPriceCents, active.
5. `backend/src/Entity/ProductKey.php`, table `product_keys`: id; offer ManyToOne Offer (not null, onDelete CASCADE); code string(64) unique (the game key text); status enumType KeyStatus, string(16), default Available; reservedUntil datetime_immutable nullable; createdAt datetime_immutable.
   Index `idx_key_offer_status` on (offer_id, status). Methods: getters plus `reserve(\DateTimeImmutable $until)`, `release()`, `markSold()` that change status/reservedUntil.
6. Repositories `ProductRepository`, `SellerRepository`, `OfferRepository`, `ProductKeyRepository` in `backend/src/Repository` (plain ServiceEntityRepository subclasses, no custom methods), referenced via `#[ORM\Entity(repositoryClass: ...)]`.
7. Generate the migration: `docker compose exec -T api php bin/console doctrine:migrations:diff -n`. Open the generated file and make sure it only creates these 4 tables/indexes/FKs (it must NOT drop or touch pg_trgm or doctrine_migration_versions). Then run `docker compose exec -T api php bin/console doctrine:migrations:migrate -n`.

Acceptance criteria (run all, all must pass)
- `docker compose exec -T api php bin/console doctrine:schema:validate` reports mapping OK and database in sync.
- `docker compose exec -T api php bin/console lint:container` passes.
- `curl -s http://localhost:8080/api/health` still returns {"status":"ok","database":"ok"}.

Do NOT
- edit anything outside `backend/src/Entity`, `backend/src/Repository`, `backend/migrations` (new file only);
- create User/Order/Payment/Banner entities (separate task);
- delete the `.gitignore` placeholder files beyond the ones in Entity/Repository; run git commit/push; touch `.env*` files; install packages.

Report: list of files created, the migration filename, and the output of schema:validate.
```

Outcome: worker hit max turns but produced the 4 entities, KeyStatus enum, 4 repositories and migration Version20261109145207. Claude review found the composite indexes were declared inside #[ORM\Table(indexes:)] (ignored by ORM 3) and hand-added to the migration after it had already run; Claude moved them to class-level #[ORM\Index]/#[ORM\UniqueConstraint], recreated the tables from the migration, and schema:validate now passes.

