<?php namespace Model\Assets\Providers;

use Model\Core\AbstractModelProvider;
use Model\Config\Config;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class ModelProvider extends AbstractModelProvider
{
	public static function realign(): void
	{
		$config = Config::get('assets');

		if (!isset($config['scss'], $config['scss']['compile'], $config['scss']['input_dir'], $config['scss']['output_dir']) or !$config['scss']['compile'])
			return;

		$scssDir = $config['scss']['input_dir'];
		$outputDir = $config['scss']['output_dir'];
		if (!$scssDir or !$outputDir)
			return;

		// Get project root path
		$scssDirFull = INCLUDE_PATH . $scssDir;
		$outputDirFull = INCLUDE_PATH . $outputDir;

		// Check if directories exist
		if (!is_dir($scssDirFull))
			return;

		if (!is_dir($outputDirFull))
			mkdir($outputDirFull, 0777, true);

		// Get all SCSS files
		$scssFiles = self::findScssFiles($scssDirFull);
		if (empty($scssFiles))
			return;

		// Calculate hash of all SCSS files
		$currentHash = self::calculateScssHash($scssFiles);
		$hashFile = $outputDirFull . DIRECTORY_SEPARATOR . '.scss_hash';

		// Check if compilation is needed
		$needsCompilation = true;
		if (file_exists($hashFile)) {
			$previousHash = file_get_contents($hashFile);
			if (!empty($previousHash) and $previousHash === $currentHash)
				$needsCompilation = false;
		}

		if ($needsCompilation) {
			$compiler = new Compiler();
			if ($config['minify_css'])
				$compiler->setOutputStyle(OutputStyle::COMPRESSED);

			foreach ($scssFiles as $scssFile) {
				// Get relative path from SCSS directory
				$relativePath = substr($scssFile, strlen($scssDirFull) + 1);

				// Get output path
				$outputPath = $outputDirFull . DIRECTORY_SEPARATOR . pathinfo($relativePath, PATHINFO_FILENAME) . '.css';

				// Only compile files that don't start with underscore (partials)
				if (!str_starts_with(basename($relativePath), '_')) {
					// Compile SCSS to CSS
					$scss = file_get_contents($scssFile);
					$compiler->setImportPaths([dirname($scssFile)]);
					$css = $compiler->compileString($scss)->getCss();

					// Write CSS to output file
					file_put_contents($outputPath, $css);
				}
			}

			// Save the hash
			file_put_contents($hashFile, $currentHash);
		}
	}

	/**
	 * Find all SCSS files in a directory recursively
	 *
	 * @param string $directory
	 * @return array
	 */
	private static function findScssFiles(string $directory): array
	{
		$directory = rtrim($directory, '/\\');
		$files = [];

		if (!is_dir($directory))
			return $files;

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($directory)
		);

		$regex = new RegexIterator($iterator, '/^.+\.scss$/i', RegexIterator::GET_MATCH);

		foreach ($regex as $file)
			$files[] = $file[0];

		return $files;
	}

	/**
	 * Calculate a hash of all SCSS files contents
	 *
	 * @param array $files
	 * @return string
	 */
	private static function calculateScssHash(array $files): string
	{
		$contents = '';
		foreach ($files as $file)
			$contents .= file_get_contents($file);
		return sha1($contents);
	}
}
