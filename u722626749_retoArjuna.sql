-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 23-05-2025 a las 18:15:20
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u722626749_retoArjuna`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_ra`
--

CREATE TABLE `usuarios_ra` (
  `usuario_id` int(10) NOT NULL,
  `usuario_nombre` char(255) NOT NULL,
  `usuario_apellido` char(255) NOT NULL,
  `usuario_correo` varchar(512) NOT NULL,
  `usuario_pass` char(100) NOT NULL,
  `usuario_fecha_nacimiento` date NOT NULL,
  `usuario_direccion_1` varchar(512) NOT NULL,
  `usuario_direccion_2` varchar(512) NOT NULL,
  `usuario_telefono` tinyint(12) NOT NULL,
  `usuario_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`usuario_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `usuarios_ra`
--
ALTER TABLE `usuarios_ra`
  ADD PRIMARY KEY (`usuario_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `usuarios_ra`
--
ALTER TABLE `usuarios_ra`
  MODIFY `usuario_id` int(10) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
