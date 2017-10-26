<?php
/**
 *
 * Copyright (c) 2017.
 * update_attributes.php
 * Code compiled by: @dbashyal 'damu' on 24/10/17 4:23 PM
 * Visit http://dltr.org for Magento Tips and Tricks
 *
 */

/*
 * die('The purpose of this file is achieved.');
 */

require_once '../app/Mage.php';
Mage::app('admin');
require_once 'abstract.php';

/**
 * Class Mage_Shell_Update_Attributes
 */
class Mage_Shell_Update_Attributes extends Mage_Shell_Abstract
{
    /**
     * @var int
     */
    private $_count     = 0;

    /**
     * @var int
     */
    public $_size       = 100;

    /**
     * @var int
     */
    public $_page       = 1;

    /**
     * @var string
     */
    public $_attr       = 'rw_google_base_skip_submi';

    /**
     * @var mixed
     */
    public $_attrVal    = 1;

    /**
     * @var array
     */
    private $_stores    = array();

    /**
     * @var array
     */
    public $_failed    = array();

    /**
     * @var string
     */
    public $_file;

    /**
     * @var string
     */
    public $_filename   = 'google_shopping.csv';

    /**
     * @return int
     */
    public function run(){
        return $this->massUpdate();
    }

    /**
     * mass update of a single attribute with common value
     * @return int
     */
    public function massUpdate(){
        $collection = Mage::getModel('catalog/product')->getCollection();
        $collection->addAttributeToSelect($this->_attr);
        //$collection->addAttributeToFilter('status', array('eq' => '1'));
        //$collection->addAttributeToFilter($this->_attr, array('neq' => $this->_attrVal)); // not working as this attribute not associated to all products.
        $collection->setPage($this->_page, $this->_size);
        $collection = $collection->load();
        $count = count($collection);

        echo 'Found total ' . $count . ' products in page '.$this->_page.' !!!' . "\n\r\n\r\n\r\n\r\n\r\n\r";

        //var_dump($collection->getSelectSql(true));

        $pid = array();
        foreach($collection as $product){
            echo (++$this->_count) . '> updating ... [' . $product->getSku() . '] ... ';

            $val = $product->getData($this->_attr);

            if($val == $this->_attrVal){
                echo "has val [{$val}] so skipped!!!\n\r\n\r";
            } else {
                $pid[$product->getSku()] = $product->getId();
                //$product->setData($this->_attr, $this->_attrVal)->save();
                echo '... stored for bulk save!!!'."\n\r\n\r";
            }
        }
        if(count($pid)){
            echo 'saving SKUs ['.implode(',', array_keys($pid)).'] ...';
            Mage::getSingleton('catalog/product_action')->updateAttributes($pid, array($this->_attr => $this->_attrVal), 0);
            echo '... saved!!!'."\n\r\n\r";
        }

        return ($this->_page > $collection->getLastPageNumber() ? 0 : $count );
    }

    /**
     * Get csv data and update product attributes
     * @return int
     */
    public function updateAttributesWithCSV(){
        echo "reading csv:\n\r";
        $rows = $this->readCSV();
        $i = 0;
        echo "found ".count($rows)." rows:\n\r";
        if(count($rows)){
            foreach($rows as $data){
                echo "checking sku '{$data['sku']}':\n\r";
                if(!isset($data['sku']) || empty($data['sku'])){
                    echo " - sku missing\n\r";
                    continue;
                }
                $product = Mage::getModel('catalog/product')->loadByAttribute('sku',$data['sku']);
                $pid = is_object($product) ? $product->getId() : 0;
                if(!$pid){
                    $this->_failed[] = $data['sku'];
                    echo " - product not found\n\r";
                    continue;
                }
                $product = Mage::getModel('catalog/product')->load($pid);
                unset($data['sku']);
                foreach(array_keys($data) as $attr){
                    echo " - saving \n\r -- attr: {$attr} \n\r -- pid {$pid} \n\r -- value: {$data[$attr]} \n\r";
                    $product->setData($attr, $data[$attr]);
                    $product->getResource()->saveAttribute($product, $attr);
                    echo " >> saved!!!\n\r\n\r";
                }
                $i++;
            }
        }
        return $i;
    }

    /**
     * Get store IDs
     * @return string
     */
    public function getStoreIds(){
        if(empty($this->_stores)){
            foreach(Mage::app()->getStores() as $store){
                $this->_stores[$store->getData('store_id')] = $store->getData('store_id');
            }
        }
        return implode(',', $this->_stores);
    }

    /**
     * read csv into array and prepare columns to update
     * @return array
     */
    public function readCSV(){
        // get original setting
        $original_auto_detect_line_endings = ini_get('auto_detect_line_endings');
        ini_set('auto_detect_line_endings',TRUE);
        $this->_file = Mage::getConfig()->getOptions()->getVarDir() . DS . 'import' . DS . $this->_filename;
        $rows = array();
        if(file_exists($this->_file) && ($handle = fopen($this->_file, "r")) !== FALSE){
            $line = 0;
            $columns = array();
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if(!$line++){
                    $columns = $data;
                    echo "columns (".implode(',', $columns).") found.:\n\r";
                    continue;
                }
                $rows[] = array_combine($columns,$data);
            }
            fclose($handle);
        } else {
            echo "file (".$this->_file.") doesn't exists or can't read.:\n\r";
        }
        // revert back to original setting.
        ini_set('auto_detect_line_endings',$original_auto_detect_line_endings);
        return $rows;
    }
}

/*let's set indexers to manual, so saving is faster*/
$processes = Mage::getSingleton('index/indexer')->getProcessesCollection();
$processes->walk('setMode', array(Mage_Index_Model_Process::MODE_MANUAL));
$processes->walk('save');

$shell = new Mage_Shell_Update_Attributes();

$massUpdateFirst = false;

$_count = 0;
if($massUpdateFirst){
    while($count = $shell->run()){
        $shell->_page++;
        $_count += $count;
        $count = $shell->run();
    }
}
$_count += $shell->updateAttributesWithCSV();

echo "... {$_count} products updated!\n\r";

$failed = $shell->_failed;
if(count($failed)){
    echo "product failed to update (".implode(',', $failed).")!\n\r";
}

/*Now set them back to auto save*/
$processes = Mage::getSingleton('index/indexer')->getProcessesCollection();
$processes->walk('setMode', array(Mage_Index_Model_Process::MODE_REAL_TIME));
$processes->walk('save');

echo 'Reindexing all...' . "\n\r";
$processes->walk('reindexAll');
echo 'Reindexing complete...' . "\n\r";

echo "........DONE.......\n\r";
