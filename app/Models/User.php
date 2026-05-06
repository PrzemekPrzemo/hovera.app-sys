<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Central\User as CentralUser;

/**
 * Alias to keep the Laravel default Auth user namespace working
 * (config/auth.php points at App\Models\User). The actual definition
 * lives in App\Models\Central\User.
 */
class User extends CentralUser
{
}
