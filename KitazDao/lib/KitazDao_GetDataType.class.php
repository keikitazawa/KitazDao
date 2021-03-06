<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . "KitazDaoBase.class.php";

/**
 * KitazDao
 * @name KitazDao_GetDataType
 * @author keikitazawa
 */
class KitazDao_GetDataType extends KitazDaoBase {
	
	/**
	 * メソッド名からクエリ種別を取得
	 * @param String $methodName メソッド文字列
	 * @return String クエリータイプ Select/Insert/Update/Delete/SQLFile
	 */
	public static function getQueryType($className, $methodName){
		
		// Insert文字列検索
		if(preg_match("/^(insert|add|create)/i", $methodName)){
			return parent::KD_STMT_INSERT;
		}
		// Update文字列検索
		if(preg_match("/^(update|modify|store)/i", $methodName)){
			return parent::KD_STMT_UPDATE;
		}
		// delete文字列検索
		if(preg_match("/^(delete|remove)/i", $methodName)){
			return parent::KD_STMT_DELETE;
		}
		// これ以外はselect文検索文字列
		return parent::KD_STMT_SELECT;
	}
	

	/**
	 * 変数からPDOデータ型を取得する
	 * @param 変数 $value
	 * @return string PDOデータ型（KD_）定数を返す
	 */
	public static function getDataType($value){
		$type = gettype($value);
		if ($type == "string"){
			return parent::KD_PARAM_STR;
		}
		if ($type == "integer" || $type == "double"){
			return parent::KD_PARAM_INT;
		}
		if ($type == "boolean"){
			return parent::KD_PARAM_BOOL;
		}
		if ($type == "NULL"){
			return parent::KD_PARAM_NULL;
		}
		// 未確定情報
		if ($type == "array" || $type == "object" || $type =="resource"){
			return parent::KD_PARAM_LOB;
		}
		// 何も該当しないものはLOB
		return parent::KD_PARAM_LOB;
	}
	
	/**
	 * PDOのデータタイプを返す
	 * @param String $className Entityのクラス名
	 * @param String $column カラム名
	 * @param Variant $value データの値
	 * @param array $sqltypeParam typeメソッドパラメータの値
	 * @return Integer KD_TYPE_* PDOのデータ型
	 */
	public static function getPDODataType($className, $column, $value, $sqltypeParam){
		/**
		 * 優先順位
		 * @since 0.5.0 最優先は$value=null;
		 * null→typeメソッドパラメータ→Entity→値判断
		 */
		if (!isset($value)){
			return KitazDao::KD_PARAM_NULL;
		}
		$evalColumn = strtoupper($column);
		// typeメソッドパラメータを優先的に取得する
		foreach ($sqltypeParam as $key => $v){
			// パラメータが存在すればこれを優先する
			if (strtoupper($key) == $evalColumn){
				return $v;
			}
		}
		// Entityクラスがあってここから定数を取得できるときにEntityからデータ型を取得する
		if (class_exists($className)){
			$ref = new ReflectionClass($className);
			if ($ref->hasConstant($evalColumn ."_TYPE")){
				return constant($className ."::". $evalColumn ."_TYPE");
			}
		}
		// Entityから取得できない場合は値から判断する
		return self::getDataType($value);
	}
}