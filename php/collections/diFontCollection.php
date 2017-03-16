<?php
/**
 * Created by diModelsManager
 * Date: 02.09.2016
 * Time: 18:42
 */

/**
 * Class diFontCollection
 * Methods list for IDE
 *
 * @method diFontCollection filterById($value, $operator = null)
 * @method diFontCollection filterByTitle($value, $operator = null)
 * @method diFontCollection filterByToken($value, $operator = null)
 * @method diFontCollection filterByWeight($value, $operator = null)
 * @method diFontCollection filterByStyle($value, $operator = null)
 * @method diFontCollection filterByLineHeight($value, $operator = null)
 * @method diFontCollection filterByContent($value, $operator = null)
 * @method diFontCollection filterByFileEot($value, $operator = null)
 * @method diFontCollection filterByFileOtf($value, $operator = null)
 * @method diFontCollection filterByFileTtf($value, $operator = null)
 * @method diFontCollection filterByFileWoff($value, $operator = null)
 * @method diFontCollection filterByFileSvg($value, $operator = null)
 * @method diFontCollection filterByTokenSvg($value, $operator = null)
 * @method diFontCollection filterByVisible($value, $operator = null)
 * @method diFontCollection filterByOrderNum($value, $operator = null)
 * @method diFontCollection filterByDate($value, $operator = null)
 *
 * @method diFontCollection orderById($direction = null)
 * @method diFontCollection orderByTitle($direction = null)
 * @method diFontCollection orderByToken($direction = null)
 * @method diFontCollection orderByWeight($direction = null)
 * @method diFontCollection orderByStyle($direction = null)
 * @method diFontCollection orderByLineHeight($direction = null)
 * @method diFontCollection orderByContent($direction = null)
 * @method diFontCollection orderByFileEot($direction = null)
 * @method diFontCollection orderByFileOtf($direction = null)
 * @method diFontCollection orderByFileTtf($direction = null)
 * @method diFontCollection orderByFileWoff($direction = null)
 * @method diFontCollection orderByFileSvg($direction = null)
 * @method diFontCollection orderByTokenSvg($direction = null)
 * @method diFontCollection orderByVisible($direction = null)
 * @method diFontCollection orderByOrderNum($direction = null)
 * @method diFontCollection orderByDate($direction = null)
 *
 * @method diFontCollection selectId()
 * @method diFontCollection selectTitle()
 * @method diFontCollection selectToken()
 * @method diFontCollection selectWeight()
 * @method diFontCollection selectStyle()
 * @method diFontCollection selectLineHeight()
 * @method diFontCollection selectContent()
 * @method diFontCollection selectFileEot()
 * @method diFontCollection selectFileOtf()
 * @method diFontCollection selectFileTtf()
 * @method diFontCollection selectFileWoff()
 * @method diFontCollection selectFileSvg()
 * @method diFontCollection selectTokenSvg()
 * @method diFontCollection selectVisible()
 * @method diFontCollection selectOrderNum()
 * @method diFontCollection selectDate()
 */
class diFontCollection extends diCollection
{
	const type = diTypes::font;
	protected $table = "fonts";
	protected $modelType = "font";
}