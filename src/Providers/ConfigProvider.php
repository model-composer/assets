<?php namespace Model\Assets\Providers;

use Model\Config\AbstractConfigProvider;

class ConfigProvider extends AbstractConfigProvider
{
	public static function migrations(): array
	{
		return [
			[
				'version' => '0.1.0',
				'migration' => function (array $config, string $env) {
					if ($config) // Already existing
						return $config;

					return [
						'force_local' => false,
						'cache_dir' => 'app-data/assets',
					];
				},
			],
			[
				'version' => '0.2.0',
				'migration' => function (array $config, string $env) {
					$config['minify_css'] = false;
					$config['minify_js'] = false;
					$config['version'] = null;
					return $config;
				},
			],
		];
	}
}
