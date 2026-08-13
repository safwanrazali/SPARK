<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilontarkan apabila penugasan entiti melanggar peraturan:
 * - pegawai yang ditugaskan bukan Pegawai Analisis
 * - entiti telah ditugaskan kepada pegawai yang sama (pendua)
 * - penukaran/penarikan penugasan sedangkan tiada penugasan aktif
 */
class InvalidAssignmentException extends RuntimeException {}
