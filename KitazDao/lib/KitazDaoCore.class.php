<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . "KitazDao_GetDataType.class.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "KitazDao_GetObject.class.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "KitazDao_OutputSelectQuery.class.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "KitazDaoBase.class.php";

/**
 * KitazDao
 * @name KitazDaoCore
 * @author keikitazawa
 */
// ----------------------------------------------------------------------------
// The MIT License (MIT)
//
// Copyright (c) 2014 keikitazawa
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
// THE SOFTWARE.

class KitazDaoCore extends KitazDaoBase {
	
	/**
	 * PDOオブジェクト
	 * @var PDOクラス
	 */
	private $pdo;
	/**
	 * DB種別
	 * @var DB種別文字列
	 *  mysql/sqlite2/sqlite/pgsql/oci/dblib/sqlsrv
	 */
	private $dbType;
	/**
	 * 処理対象になるDao
	 * @var class
	 */
	private $loadDao;
	/**
	 * 処理対象のEntity
	 * @var class
	 */
	private $loadEntity;
	
	/**
	 * construct DB接続してデータベース種別と対象のDaoを取得する
	 * @param String $className Daoクラス名
	 * @param PDO 接続済みPDOオブジェクト
	 * @return boolean falseは接続失敗
	 */
	public function __construct($className, &$pdo, $dbType){
		
		// KitazPDOからPDOオブジェクトとデータ型を受け取る
		$this->pdo = $pdo;
		$this->dbType = $dbType;
		
		//# Dao/Entityクラス取得
		// Daoクラスの取得
		$this->loadDao = KitazDao_GetObject::getDaoClass($className);
		if ($this->loadDao === false){
			throw new KitazDaoException(4);
		}
		// Daoに定義されているEntity名を取得する
		$entityName = KitazDao_GetObject::getEntityNameByDao($this->loadDao);
		if ($entityName === false){
			throw new KitazDaoException(5);
		}
		// Entityを取得
		$this->loadEntity = KitazDao_GetObject::getEntityClass($entityName);
		if ($this->loadEntity === false){
			throw new KitazDaoException(6);
		}
	}
	/**
	 * アクセス不能メソッドへのアクセス
	 * 　メソッド実行時にカスタムエラーを出力する
	 * @param String $name メソッド名
	 * @param array $arguments パラメータの配列
	 */
	public function __call($name, $arguments){
		
		// dao設定メソッドとしてアクセスする
		if (array_search($name, get_class_methods(get_class($this->loadDao))) === false){
			throw new KitazDaoException(7);
		}
		// クエリの実行
		return $this->executeMethod($name, $arguments);
	}
	
	//# private methods
	/**
	 * constructで取得したクラスのメソッドを実行
	 * @param String $methodName 実行関数名
	 * @param array $arguments 実行される関数のパラメータが格納された配列
	 * @return array 処理結果の連想配列 例）array[0]["secid"]
	 */
	private function executeMethod($methodName, $arguments){
		// DB拡張があればそれを取り込む
		$extClassName = "KitazDao_CreateQuery_" . strtolower($this->dbType);
		$extFilePath = __DIR__ . DIRECTORY_SEPARATOR . $extClassName .".class.php";
		if (file_exists($extFilePath)){
			require_once $extFilePath;
		}else {
			require_once __DIR__ . DIRECTORY_SEPARATOR ."KitazDao_CreateQuery.class.php";
			$extClassName = "KitazDao_CreateQuery";
		}
		
		// クエリ作成オブジェクトの作成
		$stmt = false;
		$queryType = KitazDao_GetDataType::getQueryType(get_class($this->loadDao), $methodName);
		$createQuery = new $extClassName($this->pdo, $this->loadDao, $this->loadEntity);
		// メソッド名からSQL種別を取得してSQL文字列を作成する
		$stmt = $createQuery->buildStatement($queryType, $methodName, $arguments);
		unset($createQuery);
		$ret = "";
		// SQL文の実行
		try {
			// no output warning for INSERT/UPDATE from OCI-CLOB
			$ret = @$stmt->execute();
		} catch (PDOException $e){
			throw new KitazDaoException(8, null, $e);
		} catch (Exception $e){
			throw new KitazDaoException(8, null, $e);
		}
		// falseの場合は実行時エラー
		if (empty($ret)){
			throw new KitazDaoException(8, "NOT RETURN EXECUTION.");
		}
		// select文は全配列を返す
		if ($queryType == parent::KD_STMT_SELECT){
			// PDOのINT/BOOL/NULL指定を行う
			$outputSelectQuery = new KitazDao_OutputSelectQuery();
			$ret = $outputSelectQuery->getArray($stmt, $this->loadEntity);
		}
		$stmt = null;
		return $ret;
	}
}
?>