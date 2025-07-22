Use easyadmin package to handle crud generation
Do not use the command make:admin:crud to create CRUD.
Avoid cleaning cache.
Avoid migrations. Use the command doctrine:schema:update --force to update the database schema.
