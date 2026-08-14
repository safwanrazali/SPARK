<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilontarkan apabila terdapat percubaan mengubah atau memadam rekod
 * jejak audit. Rekod audit hanya boleh ditambah (append-only).
 */
class ImmutableAuditLogException extends RuntimeException {}
