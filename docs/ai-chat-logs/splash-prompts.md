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


## 2026-11-09 17:06 - Order/user/payment/homepage entities

```text
Task: create the user, order, payment and homepage Doctrine entities and their migration in the Symfony 7.4 backend.

Context
- Project root: current directory. Backend is in `backend/` (Symfony 7.4, Doctrine ORM 3, PHP 8.5, PostgreSQL 16). There is NO PHP on the host: run every PHP/console command inside docker, e.g. `docker compose exec -T api php bin/console ...` (container working dir /app = backend/). Containers are running; do not stop/restart them or any other containers.
- Follow the style of the existing entities: read `backend/src/Entity/Offer.php` and `backend/src/Entity/ProductKey.php` first (attribute mapping, `declare(strict_types=1);`, typed properties, constructor-initialised required fields, getters, integer cents for money, `\DateTimeImmutable`, explicit plural table names, `#[ORM\Entity(repositoryClass: ...)]`).
- IMPORTANT: declare indexes/unique constraints as separate class-level attributes `#[ORM\Index(name: ..., columns: [...])]` / `#[ORM\UniqueConstraint(...)]`, NOT inside `#[ORM\Table(indexes: ...)]` (ORM 3 ignores those). Never hand-edit a migration after it has been executed.
- Existing migrations in `backend/migrations` are already executed; do not edit them.

Files to create (each entity gets a plain ServiceEntityRepository in `backend/src/Repository`)
1. Enums in `backend/src/Entity`: `OrderStatus: string` (pending, paid, failed, cancelled) and `PaymentStatus: string` (pending, succeeded, failed).
2. `User`, table `users`: id; email string(180) unique (store lowercased); passwordHash string(255); displayName string(80); createdAt. Setter for passwordHash and displayName.
3. `Order`, table `orders`: id; reference string(20) unique (public order number); user ManyToOne User (not null, onDelete CASCADE); status enumType OrderStatus string(16) default Pending; totalCents int; currency string(3) default 'EUR'; createdAt; paidAt datetime_immutable nullable. OneToMany `items` -> OrderItem (mappedBy order, cascade persist, orphanRemoval) initialised in constructor, with `addItem(OrderItem)` and getter. Methods `markPaid(\DateTimeImmutable $at)`, `markFailed()`, `setTotalCents(int)`. Index `idx_order_user_created` on (user_id, created_at).
4. `OrderItem`, table `order_items`: id; order ManyToOne Order (not null, onDelete CASCADE, inversedBy items); offer ManyToOne Offer (not null); productName string(200) (snapshot at purchase time); unitPriceCents int; quantity int. OneToMany `keys` -> ProductKey (mappedBy orderItem) initialised in constructor with getter.
5. Modify `backend/src/Entity/ProductKey.php`: add nullable ManyToOne `orderItem` -> OrderItem (inversedBy keys, onDelete SET NULL) with getter and `assignTo(OrderItem $item)` setter. Do not change other existing methods.
6. `Payment`, table `payments`: id; order ManyToOne Order (not null, onDelete CASCADE); provider string(32); idempotencyKey string(64) unique; status enumType PaymentStatus string(16) default Pending; amountCents int; providerReference string(64) nullable; failureReason string(255) nullable; createdAt. Methods `markSucceeded(string $providerReference)`, `markFailed(string $reason)`.
7. `Banner`, table `banners`: id; title string(120); subtitle string(255) nullable; imageUrl string(500); linkUrl string(500); position int default 0; active bool default true.
8. `HomeBlock`, table `home_blocks`: id; type string(32) (e.g. 'banners', 'best_sellers', 'deals', 'new_releases'); title string(120); position int default 0; active bool default true; settings json default [] (e.g. {"limit": 12}). Index `idx_home_block_active_position` on (active, position).
9. Generate the migration: `docker compose exec -T api php bin/console doctrine:migrations:diff -n`. Open the generated file and check it only creates these tables plus the new `order_item_id` column/FK/index on `product_keys`; it must NOT drop anything. Then `docker compose exec -T api php bin/console doctrine:migrations:migrate -n`.

Acceptance criteria (run all, all must pass)
- `docker compose exec -T api php bin/console doctrine:schema:validate` reports mapping OK and database in sync.
- `docker compose exec -T api php bin/console lint:container` passes.
- `curl -s http://localhost:8080/api/health` returns {"status":"ok","database":"ok"}.

Do NOT
- edit anything outside `backend/src/Entity`, `backend/src/Repository`, `backend/migrations` (new file only);
- install packages, touch `.env*` files, add security/auth code, run git commit/push.

Report: files created/modified, migration filename, output of schema:validate.
```

Outcome: worker stopped early (context checkpoint) after creating all 6 entities, 2 enums, 6 repositories and the ProductKey.orderItem relation, but before generating the migration. Claude review found the payments unique constraint used the property name `idempotencyKey` instead of the column `idempotency_key` (mapping error); Claude fixed it, generated migration Version20261109151512 (creates only, no drops), migrated, and schema:validate / lint:container / health all pass.

## 2026-11-09 17:15 - Fixture seed command (app:seed)

```text
Task: add a local fixture seed command `app:seed` to the Symfony 7.4 backend that fills the catalog with demo data.

Context
- Project root: current directory. Backend is in `backend/` (Symfony 7.4, Doctrine ORM 3, PHP 8.5, PostgreSQL 16). There is NO PHP on the host: run every PHP/console command inside docker, e.g. `docker compose exec -T api php bin/console ...` (container working dir /app = backend/). Containers are running; do not stop/restart them.
- Read these entities first (constructors and setters): `backend/src/Entity/Product.php`, `Seller.php`, `Offer.php`, `ProductKey.php`, `Banner.php`, `HomeBlock.php`.
- Later an IGDB importer will write the same JSON format, so the command must only read the JSON file, never call the network.

Files to create
1. `backend/data/seed/products.json`: a JSON array of 30 real, well-known video games, each object:
   {"slug": "elden-ring-pc-global", "name": "Elden Ring", "description": "<2-3 sentences>", "platform": "PC", "region": "Global", "genres": ["RPG", "Action"], "releaseDate": "2022-02-25", "coverUrl": null}
   Use platforms from: "PC", "PlayStation 5", "Xbox Series X|S", "Nintendo Switch"; regions from: "Global", "Europe", "North America". Mix them. Slugs unique, lowercase, hyphenated.
2. `backend/src/Command/SeedCommand.php`, name `app:seed`, using `#[AsCommand]`, constructor-injected `EntityManagerInterface` and `%kernel.project_dir%` (via `#[Autowire('%kernel.project_dir%')]`). Behaviour:
   - Option `--purge`: before seeding, delete all rows from product_keys, offers, products, sellers, banners, home_blocks (use DQL DELETE in that order). Without `--purge`, if any product exists, print a message and exit SUCCESS without changes (idempotent).
   - Create 5 sellers (e.g. "KeyHaven", "PixelVault", "GameDrop", "NordKeys", "LootLane") with ratings between 4.1 and 4.9.
   - For each product from the JSON: 2-4 offers from different sellers (deterministic: use `mt_srand(42)` at start and `mt_rand`), priceCents between 299 and 6999, about half with originalPriceCents 10-60% higher. Each offer gets 3-8 ProductKey rows with codes like `XXXXX-XXXXX-XXXXX` (uppercase A-Z0-9, unique; use random_bytes-based generation).
   - 3 banners (title, subtitle, imageUrl like `https://picsum.photos/seed/banner1/1200/400`, linkUrl like `/product/<slug of a seeded product>`, positions 0..2).
   - home_blocks: ('banners','Featured',0,{}), ('best_sellers','Best sellers',1,{"limit":12}), ('deals','Hot deals',2,{"limit":12}), ('new_releases','New releases',3,{"limit":12}).
   - Flush in batches (e.g. after every product), print counts at the end.

Acceptance criteria (run all, all must pass)
- `docker compose exec -T api php bin/console app:seed --purge` succeeds and prints counts (30 products, 5 sellers, offers, keys, 3 banners, 4 home blocks).
- Running `docker compose exec -T api php bin/console app:seed` again exits successfully without inserting duplicates.
- `docker compose exec -T db psql -U eneba -d eneba -c "select count(*) from products"` returns 30.
- `docker compose exec -T api php bin/console lint:container` passes.

Do NOT
- edit anything outside `backend/src/Command/`, `backend/data/seed/`;
- edit entities or migrations, install packages, touch `.env*` files, run git commit/push.

Report: files created and the seed command output.
```

Outcome: stopped by the user from the Splash menu bar before any files were written (exit 5). Not retried; waiting for the user's decision.
