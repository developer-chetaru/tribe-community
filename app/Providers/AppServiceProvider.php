<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\User; 
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //$this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        
        // Define constant for l5-swagger to use APP_URL dynamically
        // This must be defined early so l5-swagger can use it when scanning annotations
        if (!defined('L5_SWAGGER_CONST_HOST')) {
            define('L5_SWAGGER_CONST_HOST', env('APP_URL', 'http://localhost'));
        }
    }

    /**
     * Bootstrap any application services.
     */
	public function boot()
	{
    	Livewire::listen('failedAuth', function ($component, $redirect) {
        	return redirect()->route('login'); // session expire → login
    	});

        // Update Swagger API docs server URL dynamically from APP_URL
        $this->updateSwaggerServerUrl();
	}

    /**
     * Update the server URL in generated Swagger JSON file to use APP_URL from .env
     * This ensures the Swagger UI always shows the correct server URL from .env
     */
    private function updateSwaggerServerUrl(): void
    {
        $docsPath = storage_path('api-docs/api-docs.json');
        
        if (file_exists($docsPath)) {
            $appUrl = env('APP_URL', config('app.url', 'http://localhost'));
            $appUrl = rtrim($appUrl, '/');
            
            $json = json_decode(file_get_contents($docsPath), true);
            $needsUpdate = false;
            
            if (isset($json['servers']) && is_array($json['servers'])) {
                foreach ($json['servers'] as &$server) {
                    if (isset($server['url']) && $server['url'] !== $appUrl) {
                        $server['url'] = $appUrl;
                        $needsUpdate = true;
                    }
                }
                
                // Only update file if URL actually changed
                if ($needsUpdate) {
                    file_put_contents($docsPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
            }
        }
    }
}
