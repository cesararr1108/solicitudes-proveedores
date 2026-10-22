<?php
/** Solicitudes de proveedores - bandeja, historial y dashboard de mercadeo (roles 1, 118, 6). */
require dirname(__FILE__) . '/sp_layout.php';
sp_render_vista('Solicitudes de proveedores', array(1, 118, 6), array('sp-mercadeo.js'), true);
