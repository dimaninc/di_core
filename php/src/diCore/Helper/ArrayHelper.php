<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 31.08.2016
 * Time: 16:18
 */

namespace diCore\Helper;


class ArrayHelper
{
	const ESCAPE_NONE = 0;
	const ESCAPE_HTML = 1;

	public static function is($var)
	{
		return is_array($var) || (
			$var instanceof \ArrayAccess  &&
			$var instanceof \Traversable  &&
			//$var instanceof Serializable && todo: add this to diCollection!
			$var instanceof \Countable
		);
	}

	/**
	 * @param array $ar1
	 * @param array|null $ar2
	 * @return array
	 */
	public static function combine($ar1, $ar2 = null)
	{
		return $ar2 === null
			? array_combine($ar1, $ar1)
			: array_combine($ar1, $ar2);
	}

	/**
	 * @param $ar array
	 * @param $position integer
	 * @param $newItems array
	 *
	 * @return array
	 */
	public static function addItemsToAssocArray($ar, $position, $newItems)
	{
		return array_merge(
			array_slice($ar, 0, $position, true),
			$newItems,
			array_slice($ar, $position, count($ar) - $position, true)
		);
	}

	/**
	 * @param $ar
	 * @param $key
	 * @param $newItems
	 *
	 * @return array
	 * @throws \Exception
	 */
	public static function addItemsToAssocArrayAfterKey($ar, $key, $newItems)
	{
		$i = 0;

		foreach ($ar as $k => $v) {
			if ($k === $key) {
				return self::addItemsToAssocArray($ar, $i + 1, $newItems);
			}

			$i++;
		}

		throw new \Exception("No key '$key' found");
	}

	/**
	 * @param $ar
	 * @param $key
	 * @param $newItems
	 *
	 * @return array
	 * @throws \Exception
	 */
	public static function addItemsToAssocArrayBeforeKey($ar, $key, $newItems)
	{
		$i = 0;

		foreach ($ar as $k => $v) {
			if ($k === $key) {
				return self::addItemsToAssocArray($ar, $i, $newItems);
			}

			$i++;
		}

		throw new \Exception("No key '$key' found");
	}

	/**
	 * @param       $ar
	 * @param array $allowedKeys
	 * @param array $disallowedKeys
	 *
	 * @return array
	 */
	public static function filterByKey($ar, $allowedKeys = [], $disallowedKeys = [])
	{
		if ($allowedKeys)
		{
			$ar = array_intersect_key($ar, array_flip($allowedKeys));
		}

		if ($disallowedKeys)
		{
			$ar = array_diff_key($ar, array_flip($disallowedKeys));
		}

		return $ar;
	}

	/**
	 * Flattens assoc array into an attributes string
	 *
	 * @param array $ar
	 * @return string
	 */
	public static function toAttributesString($ar, $skipNull = true, $escapeMethod = self::ESCAPE_NONE)
	{
		if ($skipNull) {
			$ar = array_filter($ar, function($v) {
				return $v !== null;
			});
		}

		array_walk($ar, function(&$value, $key) use($escapeMethod) {
			$raw = is_scalar($value) && !is_bool($value);

			$quote = $raw || $escapeMethod != self::ESCAPE_NONE ? '"' : "'";

			$value = $raw
				? $value
				: json_encode($value);

			switch ($escapeMethod) {
				default:
				case self::ESCAPE_NONE:
					$value = str_replace($quote, "\\" . $quote, $value);
					break;

				case self::ESCAPE_HTML:
					$value = StringHelper::out($value);
					break;
			}

			$value = $key . '=' . $quote . $value . $quote;
		});

		return join(' ', $ar);
	}

	public static function toString($ar, $pairSymbol = '=', $spaceSymbol = ' ')
    {
        $ar2 = [];

        foreach ($ar as $k => $v) {
            $ar2[] = $k . $pairSymbol . $v;
        }

        return join($spaceSymbol, $ar2);
    }

	/**
	 * Returns element of array by idx, setting proper type, if it exists, else returns default value
	 *
	 * @param mixed $ar
	 * @param int|string|bool $idx
	 * @param mixed $defaultValue
	 * @param string $type
	 * @return mixed
	 */
	public static function getValue($ar, $idx, $defaultValue = null, $type = null)
	{
	    $ar = (array)$ar;

		$type = $type ?: gettype($defaultValue);

		if (isset($ar[$idx])) {
			$value = $ar[$idx];

			if ($type != 'NULL' && is_scalar($value)) {
				settype($value, $type);
			}

			return $value;
		} else {
			return $defaultValue;
		}
	}

	/**
	 * @param array $array
	 * @return array
	 */
	public static function shuffleAssoc($array)
	{
		$new = array();
		$keys = array_keys($array);
		shuffle($keys);

		foreach($keys as $key) {
			$new[$key] = $array[$key];
		}

		return $new;
	}

	/**
	 * @param array $array
	 * @param mixed $value
	 * @return array
	 */
	public static function removeByValue($array, $value)
	{
		if (($key = array_search($value, $array)) !== false) {
			unset($array[$key]);
		}

		return $array;
	}

	/**
	 * @param array $ar
	 * @param string $defaultGlue
	 *
	 * @return string
	 */
	public static function recursiveJoin(array $ar, $defaultGlue = ' ')
	{
		$ar = array_filter($ar);

		foreach ($ar as $glue => &$value) {
		    // assoc array has `glue => array` structure, using default glue for simple arrays
		    if (!self::isAssoc($ar)) {
		        $glue = $defaultGlue;
            }

			if (is_array($value)) {
				$value = self::recursiveJoin($value, $glue);
			}
		}

		return join($defaultGlue, array_filter($ar));
	}

	/**
	 * Returns random $count elements from array
	 *
	 * @param array $ar
	 * @param int $count
	 *
	 * @return array
	 */
	public static function random(array $ar, $count = 1)
	{
		if ($count >= count($ar)) {
			return $ar;
		}

		$keys = array_rand($ar, $count);
		$out = [];

		foreach ($keys as $key) {
			$out[] = $ar[$key];
		}

		return $out;
	}

    public static function isAssoc(array $ar)
    {
        if ([] === $ar) {
            return false;
        }

        return array_keys($ar) !== range(0, count($ar) - 1);
    }

    public static function mapAssoc(callable $f, array $a)
    {
        return array_column(array_map($f, array_keys($a), $a), 1, 0);
    }
}