<?php namespace Model\Assets;

use Model\Config\Config;

class Assets
{
	private static array $files = [];
	private static array $enabled = [];

	/**
	 * @param string $what
	 * @param int|null $version
	 * @return void
	 */
	public static function enable(string $what, ?int $version = null): void
	{
		if (array_key_exists($what, self::$enabled))
			return;

		switch ($what) {
			case 'bootstrap':
				$version ??= 5;

				switch ($version) {
					case 4:
						self::enable('jquery', 3);
						self::add('https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css');
						self::add('https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js');
						break;

					case 5:
						self::add('https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/css/bootstrap.min.css');
						self::add('https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/js/bootstrap.bundle.min.js');
						break;

					default:
						throw new \Exception('Unknown Bootstrap version');
				}
				break;

			case 'jquery':
				$version ??= 3;

				switch ($version) {
					case 3:
						self::add('https://code.jquery.com/jquery-3.6.1.min.js');
						break;

					default:
						throw new \Exception('Unknown JQuery version');
				}
				break;

			default:
				throw new \Exception('Unsupported assets library');
		}

		self::$enabled[$what] = $version;
	}

	/**
	 * Add a file to the render list
	 *
	 * @param string $file
	 * @param array $options
	 */
	public static function add(string $file, array $options = []): void
	{
		$options = array_merge([
			'type' => null,
			'withTags' => [],
			'exceptTags' => [],
			'custom' => true,
			'cacheable' => true,
			'defer' => false,
			'async' => false,
			'version' => null,
		], $options);

		$ext = strtolower(pathinfo(parse_url($file)['path'], PATHINFO_EXTENSION));
		if (!in_array($ext, ['js', 'css']))
			$options['cacheable'] = false;

		if ($options['type'] === null)
			$options['type'] = $ext;

		if (!in_array($options['type'], ['css', 'js']))
			throw new \Exception('Invalid asset type');

		if (!is_array($options['withTags']))
			$options['withTags'] = [$options['withTags']];
		if (!is_array($options['exceptTags']))
			$options['exceptTags'] = [$options['exceptTags']];

		if (!in_array('position-head', $options['withTags']) and !in_array('position-foot', $options['withTags']))
			$options['withTags'][] = 'position-head';

		if (!isset(self::$files[$file]))
			self::$files[$file] = $options;
	}

	/**
	 * Remove file from the render list
	 *
	 * @param string $file
	 */
	public static function remove(string $file): void
	{
		if (!isset(self::$files[$file]))
			unset(self::$files[$file]);
	}

	/**
	 * Removes all files set by the user
	 */
	public static function wipe(): void
	{
		foreach (self::$files as $file => $options) {
			if ($options['custom'])
				self::remove($file);
		}
	}

	/**
	 * @param array $tags
	 * @param bool $forCache
	 * @return array
	 */
	public static function getList(array $tags = [], bool $forCache = false): array
	{
		$list = [];
		foreach (self::$files as $file => $options) {
			if ($forCache) {
				if ($options['cacheable'])
					$list[$file] = $options;
			} else {
				if (!empty($options['withTags'])) {
					$allowed = false;
					foreach ($options['withTags'] as $tag) {
						if (in_array($tag, $tags)) {
							$allowed = true;
							break;
						}
					}

					if (!$allowed)
						continue;
				}

				if (!empty($options['exceptTags'])) {
					$allowed = true;
					foreach ($options['exceptTags'] as $tag) {
						if (in_array($tag, $tags)) {
							$allowed = false;
							break;
						}
					}

					if (!$allowed)
						continue;
				}

				$list[$file] = $options;
			}
		}

		return $list;
	}

	/**
	 * Render all added files given the tags
	 *
	 * @param array $tags
	 * @return void
	 */
	public static function render(array $tags = []): void
	{
		$config = self::getConfig();
		$list = self::getList($tags);

		$toMinify = [
			'css' => [],
			'js' => [],
		];

		foreach ($list as $file => $options) {
			$parsedFile = self::parse($file, $options['cacheable']);
			if (!$config['minify_' . $options['type']] or ($parsedFile['is_remote'] and !$parsedFile['local']) or !$options['cacheable'] or (defined('DEBUG_MODE') and DEBUG_MODE)) {
				self::renderFile($config, $parsedFile, $options);
			} else {
				$group = (int)$options['async'] . '-' . (int)$options['defer'];
				if (!isset($toMinify[$options['type']][$group])) {
					$toMinify[$options['type']][$group] = [
						'async' => $options['async'],
						'defer' => $options['defer'],
						'files' => [],
					];
				}
				$toMinify[$options['type']][$group]['files'][] = $parsedFile['local'] ?? $file;
			}
		}

		if (!empty($toMinify['css']) or !empty($toMinify['js'])) {
			$directories = self::getDirectories();
			$minifyDir = $directories['cache_dir'] . 'minify';
			$minifyDirFull = $directories['cache_full'] . 'minify';
			if (!is_dir($minifyDirFull))
				mkdir($minifyDirFull, 0777, true);

			foreach ($toMinify as $type => $groups) {
				foreach ($groups as $group) {
					$minifiedFile = sha1(implode('', $group['files']) . $config['version']) . '.' . $type;

					if (!file_exists($minifyDirFull . DIRECTORY_SEPARATOR . $minifiedFile)) {
						$minifier = match ($type) {
							'css' => new \MatthiasMullie\Minify\CSS(),
							'js' => new \MatthiasMullie\Minify\JS(),
							default => throw new \Exception('Unknown minified file type'),
						};
						foreach ($group['files'] as $file)
							$minifier->add(parse_url($directories['project_root'] . $file)['path']);
						$minifier->minify($minifyDirFull . DIRECTORY_SEPARATOR . $minifiedFile);
					}

					self::renderFile($config, self::parse($minifyDir . DIRECTORY_SEPARATOR . $minifiedFile), [
						'async' => $group['async'],
						'defer' => $group['defer'],
					]);
				}
			}
		}
	}

	/**
	 * @param array $config
	 * @param array $parsedFile
	 * @param array $options
	 * @return void
	 */
	private static function renderFile(array $config, array $parsedFile, array $options = []): void
	{
		$options = array_merge([
			'type' => null,
			'async' => false,
			'defer' => false,
			'version' => null,
		], $options);

		if (!in_array($options['type'], ['css', 'js']))
			throw new \Exception('Invalid asset type');

		$displayPath = $parsedFile['path'];
		if ($parsedFile['is_remote'] and $config['force_local']) {
			if ($parsedFile['local']) {
				$parsedFile['is_remote'] = false;
				$displayPath = $parsedFile['local'];
			} else {
				throw new \Exception('Cannot render file "' . $parsedFile['path'] . '"; remote disabled, local file not found');
			}
		}

		if ($options['version'] ?? $config['version'])
			$displayPath .= '?v=' . ($options['version'] ?? $config['version']);

		switch ($options['type']) {
			case 'css':
				$failover = ($parsedFile['is_remote'] and $parsedFile['local']) ? ' onerror="this.onerror=null;this.href=\'' . $parsedFile['local'] . '\';"' : '';

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
				break;

			case 'js':
				?>
				<script type="text/javascript" src="<?= $displayPath ?>"<?= $options['defer'] ? ' defer' : '' ?><?= $options['async'] ? ' async' : '' ?>></script>
				<?php
				break;
		}
	}

	/**
	 * @param string $path
	 * @param bool $cacheable
	 * @return array
	 */
	private static function parse(string $path, bool $cacheable = true): array
	{
		$directories = self::getDirectories();

		if (str_starts_with(strtolower($path), 'http://') or str_starts_with(strtolower($path), 'https://') or str_starts_with($path, '//')) {
			$parsed_url = parse_url($path);

			if ($cacheable and in_array(strtolower(pathinfo($parsed_url['path'], PATHINFO_EXTENSION)), ['js', 'css'])) {
				// Only js and css can be cached locally
				$localFile = $parsed_url['host'] . DIRECTORY_SEPARATOR . $parsed_url['path'];
				if (!file_exists($directories['cache_full'] . $localFile)) {
					$cacheDir = pathinfo($directories['cache_full'] . $localFile, PATHINFO_DIRNAME);
					if (!is_dir($cacheDir))
						mkdir($cacheDir, 0777, true);

					file_put_contents($directories['cache_full'] . $localFile, file_get_contents($path));
				}

				return [
					'path' => $path,
					'is_remote' => true,
					'local' => (defined('PATH') ? PATH : '') . $directories['cache_dir'] . $localFile,
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
	 * Retrieves cache directory path
	 *
	 * @return array
	 */
	private static function getDirectories(): array
	{
		$config = self::getConfig();

		$projectRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR;

		if (!is_dir($projectRoot . $config['cache_dir']))
			mkdir($projectRoot . $config['cache_dir'], 0777, true);

		return [
			'project_root' => $projectRoot,
			'cache_dir' => $config['cache_dir'] . DIRECTORY_SEPARATOR,
			'cache_full' => $projectRoot . $config['cache_dir'] . DIRECTORY_SEPARATOR,
		];
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
		]);
	}
}
