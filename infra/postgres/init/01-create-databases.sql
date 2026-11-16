-- One database per service. Runs only when the Postgres volume is first created.
CREATE DATABASE auth;
CREATE DATABASE catalog;
CREATE DATABASE orders;
