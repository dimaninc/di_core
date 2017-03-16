<?php
/**
 * Created by diModelsManager
 * Date: 17.07.2015
 * Time: 14:12
 */

/**
 * Class diFontModel
 * Methods list for IDE
 *
 * @method string	getTitle
 * @method string	getToken
 * @method string	getWeight
 * @method string	getStyle
 * @method string	getContent
 * @method string	getFileEot
 * @method string	getFileOtf
 * @method string	getFileTtf
 * @method string	getFileWoff
 * @method string	getFileSvg
 * @method string	getTokenSvg
 * @method integer	getVisible
 * @method integer	getOrderNum
 * @method string	getDate
 *
 * @method bool hasTitle
 * @method bool hasToken
 * @method bool hasWeight
 * @method bool hasStyle
 * @method bool hasContent
 * @method bool hasFileEot
 * @method bool hasFileOtf
 * @method bool hasFileTtf
 * @method bool hasFileWoff
 * @method bool hasFileSvg
 * @method bool hasTokenSvg
 * @method bool hasVisible
 * @method bool hasOrderNum
 * @method bool hasDate
 *
 * @method diFontModel setTitle($value)
 * @method diFontModel setToken($value)
 * @method diFontModel setWeight($value)
 * @method diFontModel setStyle($value)
 * @method diFontModel setContent($value)
 * @method diFontModel setFileEot($value)
 * @method diFontModel setFileOtf($value)
 * @method diFontModel setFileTtf($value)
 * @method diFontModel setFileWoff($value)
 * @method diFontModel setFileSvg($value)
 * @method diFontModel setTokenSvg($value)
 * @method diFontModel setVisible($value)
 * @method diFontModel setOrderNum($value)
 * @method diFontModel setDate($value)
 */
class diFontModel extends diModel
{
	const type = diTypes::font;
	protected $table = "fonts";
	protected $slugFieldName = "token";
}