# CLI Scripts - AI Auto Blog

## cron-generate.php

Script principal para generación automática desde system cron.

**Uso:**
```bash
0 9 * * * /usr/bin/php /path/to/wp-content/plugins/ai-auto-blog/cron-generate.php >> /tmp/ai-auto-blog-cron.log 2>&1
```

## cron-test.php.example

Script de prueba para verificar configuración antes de usar system cron.

**Para usar:**
1. Renombrar `cron-test.php.example` a `cron-test.php`
2. Ejecutar: `php cron-test.php`
3. Verificar la salida
4. Eliminar o renombrar de vuelta a `.example`

**Nota:** Este archivo tiene extensión `.example` porque WordPress.org Plugin Check no permite scripts CLI sin protección ABSPATH (que estos scripts no pueden tener porque se ejecutan ANTES de cargar WordPress).

## Documentación Completa

Ver `INSTALACION-CRON-SISTEMA.md` en el directorio de outputs para instrucciones completas de configuración.
