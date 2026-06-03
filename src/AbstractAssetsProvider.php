<?php namespace Model\Assets;

use Model\ProvidersFinder\AbstractProvider;

abstract class AbstractAssetsProvider extends AbstractProvider
{
	/**
	 * Returns a list of asset libraries defined by the package.
	 *
	 * Each library is an associative array with:
	 *  - 'name' (string, required): unique name used with Assets::enable
	 *  - 'auto_enable' (bool, optional, default false): if true the library is
	 *    enabled automatically; otherwise consumers must call Assets::enable($name)
	 *  - 'files' (array, optional): list of files to add when the library is enabled.
	 *    Each entry is either a path string or an associative array with a 'path' key
	 *    plus any option accepted by Assets::add (type, defer, async, withTags, ...).
	 *
	 * @return array
	 */
	public static function assets(): array
	{
		return [];
	}
}
