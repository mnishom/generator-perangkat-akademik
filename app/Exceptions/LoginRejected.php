<?php

namespace App\Exceptions;

use RuntimeException;

/** Login ditolak; pesannya aman ditampilkan ke pengguna. */
class LoginRejected extends RuntimeException {}
