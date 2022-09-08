<?php namespace Model\Assets;

use Model\Config\Config;

class Assets
{
	/**
	 * @param string $path
	 * @param array $options
	 * @return void
	 */
	public static function renderJs(string $path, array $options = []): void
	{
		$options = array_merge([
			'async' => false,
			'defer' => false,
		], $options);

		$config = self::getConfig();

		$parsed = self::get($path);

		$displayPath = $parsed['path'];
		if ($parsed['is_remote'] and $config['force_local']) {
			if ($parsed['local']) {
				$parsed['is_remote'] = false;
				$displayPath = $parsed['local'];
			} else {
				throw new \Exception('Cannot render js script "' . $path . '"; remote disabled, local file not found');
			}
		}
		?>
		<script type="text/javascript" src="<?= $displayPath ?>"<?= $options['defer'] ? ' defer' : '' ?><?= $options['async'] ? ' async' : '' ?>></script>
		<?php
	}

	/**
	 * @param string $path
	 * @param array $options
	 * @return void
	 */
	public static function renderCss(string $path, array $options = []): void
	{
		$options = array_merge([
			'defer' => false,
		], $options);

		$config = self::getConfig();

		$parsed = self::get($path);

		$displayPath = $parsed['path'];
		if ($parsed['is_remote'] and $config['force_local']) {
			if ($parsed['local']) {
				$parsed['is_remote'] = false;
				$displayPath = $parsed['local'];
			} else {
				throw new \Exception('Cannot render css file "' . $path . '"; remote disabled, local file not found');
			}
		}

		$failover = ($parsed['is_remote'] and $parsed['local']) ? ' onerror="this.onerror=null;this.href=\'' . $parsed['local'] . '\';"' : '';

		if ($options['defer']) {
			?>
			<link rel="preload" href="<?= $displayPath ?>" as="style" onload="this.onload=null;this.rel='stylesheet'"<?= $failover ?>/>
			<noscript>
				<link rel="stylesheet" type="text/css" href="<?= $displayPath ?>"<?= $failover ?>/>
			</noscript>
			<?php
		} else {
			?>
			<link rel="stylesheet" type="text/css" href="<?= $displayPath ?>"<?= $failover ?>/>
			<?php
		}
	}

	/**
	 * @param string $path
	 * @return array
	 */
	public static function get(string $path): array
	{
		if (str_starts_with(strtolower($path), 'http://') or str_starts_with(strtolower($path), 'https://') or str_starts_with($path, '//')) {
			$parsed_url = parse_url($path);

			if (in_array(strtolower(pathinfo($parsed_url['path'], PATHINFO_EXTENSION)), ['js', 'css'])) {
				// Only js and css can be cached locally
				$config = self::getConfig();

				$localFile = $config['cache_dir'] . DIRECTORY_SEPARATOR . $parsed_url['host'] . DIRECTORY_SEPARATOR . $parsed_url['path'];
				if (!file_exists(self::getProjectRoot() . $localFile)) {
					$cacheDir = pathinfo(self::getProjectRoot() . $localFile, PATHINFO_DIRNAME);
					if (!is_dir($cacheDir))
						mkdir($cacheDir, 0777, true);

					file_put_contents(self::getProjectRoot() . $localFile, file_get_contents($path));
				}

				return [
					'path' => $path,
					'is_remote' => true,
					'local' => defined('PATH') ? PATH . $localFile : $localFile,
				];
			} else {
				return [
					'path' => $path,
					'is_remote' => true,
					'local' => null,
				];
			}
		} else {
			if (defined('PATH'))
				$path = PATH . $path;

			return [
				'path' => $path,
				'is_remote' => false,
				'local' => null,
			];
		}
	}

	/**
	 * Retrieves project root
	 *
	 * @return string
	 */
	private static function getProjectRoot(): string
	{
		return realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public static function getConfig(): array
	{
		return Config::get('assets', [
			[
				'version' => '0.1.0',
				'migration' => function (array $config, string $env) {
					if ($config) // Already existing
						return $config;

					return [
						'force_local' => false,
						'cache_dir' => 'app/assets/cache',
					];
				},
			],
		]);
	}
}
