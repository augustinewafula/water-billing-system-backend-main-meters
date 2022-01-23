<?php

// @formatter:off

/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models {

    use Eloquent;
    use Illuminate\Database\Eloquent\Builder;
    use Illuminate\Support\Carbon;

    /**
     * App\Models\Meter
     *
     * @property string $id
     * @property string $number
     * @property int $valve_status
     * @property string $station_id
     * @property string|null $type
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @method static Builder|Meter newModelQuery()
     * @method static Builder|Meter newQuery()
     * @method static Builder|Meter query()
     * @method static Builder|Meter whereCreatedAt($value)
     * @method static Builder|Meter whereId($value)
     * @method static Builder|Meter whereNumber($value)
     * @method static Builder|Meter whereStationId($value)
     * @method static Builder|Meter whereType($value)
     * @method static Builder|Meter whereUpdatedAt($value)
     * @method static Builder|Meter whereValveStatus($value)
     */
    class Meter extends Eloquent
    {
    }
}

namespace App\Models {

    use Eloquent;
    use Illuminate\Database\Eloquent\Builder;
    use Illuminate\Support\Carbon;

    /**
     * App\Models\MeterStation
     *
     * @property string $id
     * @property string $name
     * @property int $type
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @method static Builder|MeterStation newModelQuery()
     * @method static Builder|MeterStation newQuery()
     * @method static Builder|MeterStation query()
     * @method static Builder|MeterStation whereCreatedAt($value)
     * @method static Builder|MeterStation whereId($value)
     * @method static Builder|MeterStation whereName($value)
     * @method static Builder|MeterStation whereType($value)
     * @method static Builder|MeterStation whereUpdatedAt($value)
     */
    class MeterStation extends Eloquent
    {
    }
}

namespace App\Models {

    use Eloquent;
    use Illuminate\Database\Eloquent\Builder;
    use Illuminate\Support\Carbon;

    /**
     * App\Models\MeterType
     *
     * @property string $id
     * @property string $name
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @method static Builder|MeterType newModelQuery()
     * @method static Builder|MeterType newQuery()
     * @method static Builder|MeterType query()
     * @method static Builder|MeterType whereCreatedAt($value)
     * @method static Builder|MeterType whereId($value)
     * @method static Builder|MeterType whereName($value)
     * @method static Builder|MeterType whereUpdatedAt($value)
     */
    class MeterType extends Eloquent
    {
    }
}

namespace App\Models {

    use Database\Factories\UserFactory;
    use Eloquent;
    use Illuminate\Database\Eloquent\Builder;
    use Illuminate\Database\Eloquent\Collection;
    use Illuminate\Notifications\DatabaseNotification;
    use Illuminate\Notifications\DatabaseNotificationCollection;
    use Illuminate\Support\Carbon;
    use Laravel\Passport\Client;
    use Laravel\Passport\Token;
    use Spatie\Permission\Models\Permission;
    use Spatie\Permission\Models\Role;

    /**
     * App\Models\User
     *
     * @property string $id
     * @property string $name
     * @property string $email
     * @property Carbon|null $email_verified_at
     * @property string $password
     * @property string|null $meter_id
     * @property string|null $first_bill
     * @property string|null $remember_token
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read Collection|Client[] $clients
     * @property-read int|null $clients_count
     * @property-read DatabaseNotificationCollection|DatabaseNotification[] $notifications
     * @property-read int|null $notifications_count
     * @property-read Collection|Permission[] $permissions
     * @property-read int|null $permissions_count
     * @property-read Collection|Role[] $roles
     * @property-read int|null $roles_count
     * @property-read Collection|Token[] $tokens
     * @property-read int|null $tokens_count
     * @method static UserFactory factory(...$parameters)
     * @method static Builder|User newModelQuery()
     * @method static Builder|User newQuery()
     * @method static Builder|User permission($permissions)
     * @method static Builder|User query()
     * @method static Builder|User role($roles, $guard = null)
     * @method static Builder|User whereCreatedAt($value)
     * @method static Builder|User whereEmail($value)
     * @method static Builder|User whereEmailVerifiedAt($value)
     * @method static Builder|User whereFirstBill($value)
     * @method static Builder|User whereId($value)
     * @method static Builder|User whereMeterId($value)
     * @method static Builder|User whereName($value)
     * @method static Builder|User wherePassword($value)
     * @method static Builder|User whereRememberToken($value)
     * @method static Builder|User whereUpdatedAt($value)
     */
    class User extends Eloquent
    {
    }
}

