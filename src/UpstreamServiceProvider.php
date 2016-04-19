<?php namespace Regulus\Upstream;

use Illuminate\Support\ServiceProvider;

class UpstreamServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->publishes([
			__DIR__.'/config/upload.php'  => config_path('upload.php'),
		]);

		$this->loadTranslationsFrom(__DIR__.'/lang', 'upstream');

		$this->publishes([
			__DIR__.'/lang' => resource_path('lang/vendor/upstream'),
		]);
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// bind Upstream
		$this->app->singleton('Regulus\Upstream\Upstream', function()
		{
			return new Upstream;
		});

		// register additional service providers
		$this->app->register('Intervention\Image\ImageServiceProvider');
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return [];
	}

}