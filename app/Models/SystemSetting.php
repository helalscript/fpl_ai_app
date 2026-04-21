<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'system_settings';

    protected $fillable = [
        'title',
        'system_name',
        'email',
        'contact_number',
        'company_open_hour',
        'copyright_text',
        'logo',
        'favicon',
        'address',
        'description',
        'footer_logo',
        'paypal_client_id',
        'paypal_client_secret',
        'stripe_public_key',
        'stripe_secret_key',
        'ai_api_key',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_address',
        'smtp_from_name',
    ];

    protected $casts = [
        'id' => 'integer',
        'title' => 'string',
        'system_name' => 'string',
        'email' => 'string',
        'contact_number' => 'string',
        'company_open_hour' => 'string',
        'copyright_text' => 'string',
        'logo' => 'string',
        'favicon' => 'string',
        'address' => 'string',
        'description' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'footer_logo' => 'string',
        'paypal_client_id' => 'string',
        'paypal_client_secret' => 'string',
        'stripe_public_key' => 'string',
        'stripe_secret_key' => 'string',
        'ai_api_key' => 'string',
        'smtp_host' => 'string',
        'smtp_port' => 'integer',
        'smtp_username' => 'string',
        'smtp_password' => 'string',
        'smtp_encryption' => 'string',
        'smtp_from_address' => 'string',
        'smtp_from_name' => 'string',
    ];

    public function getFileUrlAttribute($value): ?string
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        if (request()->is('api/*') && ! empty($value)) {
            return url($value);
        }

        return $value;
    }

    // Use this function in your existing attributes
    public function getLogoAttribute($value): ?string
    {
        return $this->getFileUrlAttribute($value);
    }

    public function getFaviconAttribute($value): ?string
    {
        return $this->getFileUrlAttribute($value);
    }

    public function getFooterLogoAttribute($value): ?string
    {
        return $this->getFileUrlAttribute($value);
    }
}
