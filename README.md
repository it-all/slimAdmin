# Slim-Postgres  
Slim-Postgres is a <a target="_blank" href="https://www.php.net">PHP</a> framework based on <a target="_blank" href="https://www.slimframework.com/">Slim Micro-Framework</a> and <a target="_blank" href="https://www.postgresql.org/">PostgreSQL</a>. It has a built-in administrative interface and other tools to allow rapid web app development.  
  
INSTALLATION & USAGE DOCUMENTATION  
See <a href="https://github.com/it-all/slim-postgres-skeleton">Slim-Postgres-Skeleton</a>.  

CODE DOCUMENTATION  
Entities  
Entities are like business objects at the core of the system. The current entities are Administrators, Roles, Permissions, and Events. Domain business object, i.e. Orders in an ecommerce system, will go in the domain directory of <a href="https://github.com/it-all/slim-postgres-skeleton">Slim-Postgres-Skeleton</a>.  
  
Database Mappers  
Mappers are where all PostgreSQL database queries should occur. In fact, where all database function calls (pg_*) should occur.  There are two types of mappers: table mappers and entity mappers. 
  
Table Mappers  
Table mappers consist mainly of select, insert, update and delete functions for a single database table. Select functions return an array of record arrays (using pg_fetch_all) or a single record array, or null if no records match the query. Insert functions return the primary key value inserted. Update and delete functions have no return value. All failed queries result in an exception being thrown.  

Entity Mappers  
Entity mappers are like table mappers for more complex entities requiring two or more database tables. For example, every administrator must have one or more assigned roles, so inserting an administrator is done using an entity mapper rather than a table mapper. The entity mapper will be responsible for inserting both the administrator roles and the administrator, using a transaction. It can call table mappers to perform the single table queries. Entity mappers can also return entity objects, i.e. an Administrator or an array of Administrators.  