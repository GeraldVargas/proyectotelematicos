-- Base aparte para los tests de la API de Ubicación (RefreshDatabase la vacía en cada corrida)
CREATE DATABASE ubicacion_test;
\c ubicacion_test
CREATE EXTENSION IF NOT EXISTS postgis;
