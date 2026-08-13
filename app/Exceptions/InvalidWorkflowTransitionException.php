<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilontarkan apabila peralihan peringkat workflow melanggar peraturan:
 * - lompatan peringkat yang tidak berturutan
 * - peringkat di luar julat 1–7
 * - pengunduran tanpa sebab
 * - status tidak sah
 */
class InvalidWorkflowTransitionException extends RuntimeException {}
