#!/usr/bin/php5
<?php
/**
*This script will fetch and parse a table from a web page
*Columns of the table can then be echoed out to the terminal
*
*@author Daniel Renaud
*
*/

/**
*For final turn down error reporting
*This suppresses errors from invalid HTML
*/
//error_reporting(E_ERROR);

$url = "http://www.usinflationcalculator.com/inflation/historical-inflation-rates/";
$ch = curl_init();

curl_setopt_array($ch, array(
			CURLOPT_URL=>$url,
			CURLOPT_RETURNTRANSFER=>True)
			);
			
/*Pull down HTML page source from URL*/
$html = curl_exec($ch);

curl_close($ch);

/*Create DOMDocument object and load in HTML*/
$dom = new DOMDocument;
$dom->loadHTML($html);

/**
*Create HTMLTableParser instance
*This is a self-defined class that is defined at the bottom of this script
*/
$tableParser = new HTMLTableParser;

/**
*Find the desired table within the DOMDocument
*Usually we could get the desired table by using getElementByID
*But the table has no ID, so we know from examining the source that we want the first table in the html
*/
$htmlTables = $dom->getElementsByTagName('table');
$inflationTable = $htmlTables->item(0);

/*Double check that what we have is actually a table*/
if($inflationTable->tagName == "table"){
	/*Set the table property for our HTMLTableParser object*/
	 $tableParser->setTable($inflationTable);
}
/*Parse the table in to an array that is easy to access*/
$tableParser->parseTable();

/**
*Specify the columns we want to output
*Pass Null as columns argument to output all columns
*/
$outColumns = array('Year', 'Ave');
$tableParser->renderTable($outColumns, 'Ave', 'DESC');



/**
*HTMLTableParser Class
*
*This class is given a DOMNode object that references an html table
*The class includes methods to parse the table into a PHP array
*The first row (Column Headers) of the table becomes the array keys for each row array
*
*/
class HTMLTableParser{
	
	/*Define class properties*/
	public $table; //DOMNode
	public $tbody; //DOMNode
	public $rows = array();
	public $rowCount = 0;
	public $columnHeaders = array();
	public $columnCount = 0;
	
	public function __construct(DOMNode $table = null){
		/**
		*The contructor sets the $table property and the $tbody since that is more useful for parsing
		*@param DOMNode $table the html table to work with
		*/
		if ($table instanceof DOMNode){
			$this->table = $table;
			$this->tbody = $table->childNodes->item(0);
		}
	}
	
	public function setTable(DOMNode $table){
		/**
		*Used to set the $table and $tbody properties after construction
		*
		*@param DOMNode $table the html table to work with
		*@return bool return true if valid DOMNode was passed
		*/
		if ($table instanceof DOMNode){
			$this->table = $table;
			$this->tbody = $table->childNodes->item(0);
			return True;
		}else{
			return False;
		}
	}
	
	public function renderTable(Array $columns = null, $sort = 'Year', $order = 'ASC'){
		/**
		*Print specified table columns to the console
		*This function also adds some extra formatting to make the table more legible
		*
		*@param array $columns the columns to be included in the rendering, use all columns if none provided
		*@param string $sort the column by which to sort the table
		*@param string $order determines if the sort is ascending or descending. Valid args are 'ASC' and 'DESC', invalid arg defaults to 'ASC'
		*/
		$output = "";
		if( ! in_array($sort, $this->columnHeaders)){
		//Not a valid sort column; Use default sort (Year)
			$sort= 'Year';
		}
		$sortColumn = $this->getColumn($sort);
		if($order == 'DESC'){
			arsort($sortColumn);
		}else{
			asort($sortColumn);
		}
		if( ! $columns OR ! is_array($columns)){//If no columns specified, render all columns
			$columns = $this->columnHeaders;
		}
		$padLength = max(array_map('strlen', $columns)) + 2;

		//render the column headers
		foreach($columns as $k=>$col){
			if( ! in_array($col, $this->columnHeaders) ){//Check that column is valid
				//Get rid of invalid output column
				unset($columns[$k]);
			}else{//add to column header output
				$output .= str_pad($col, $padLength, " ", STR_PAD_BOTH) . "|";
			}
		}
		//Horizontal rule below header
		$output .= "\n" . str_repeat("-", ($padLength + 1) * sizeOf($columns));
		//Render the rows of the table
		foreach($sortColumn as $order=>$val){
			$output .= "\n";
			foreach($columns as $key=>$col){
				$output .= str_pad($this->rows[$order][$col], $padLength, " ", STR_PAD_BOTH) . "|";
			}
		}
		echo $output;
	}
	
	public function getColumn( $columnKey ){
	/**
	*Retrieves all rows for a single column
	*@param string $columnKey the columnHeader for the column to retrieve
	*@return array 
	*/
		$column = array();
		foreach($this->rows as $i=>$row){
			$column[] = $row[$columnKey];
		}
		return $column;
	}
	
	public function parseTable(){
		/**
		*Combines parseColumnHeaders and parseRows into one simple method
		*/
		$this->parseColumnHeaders();
		$this->parseRows();
	}
	
	public function parseColumnHeaders(){
	/**
	*This method will look at the first row of the table, assumes it is the header
	*And parses this row to set the columnHeaders property
	*Result columnHeaders is an array of strings
	*/
		$firstColumn = $this->tbody->childNodes->item(0);
		$col = 0;
		for($i = 0; $i < $firstColumn->childNodes->length; $i++){
			$cell = $firstColumn->childNodes->item($i);
			if(isset($cell->tagName)){
				if($cell->tagName == "th"){
					$this->columnHeaders[] = $cell->textContent;
					$col++;
					$this->columnCount += 1;
				}
			}
		}
	}
	
	public function parseRows(){
	/**
	*This method will walk through the entire table and set up a large array
	*The resulting array with have an array for each row
	*Each row array will be an associative array where the keys are the column headers 
	*	and the values are the content of the td elements
	*The method sets the rows property for the object
	*/
		/*We need to set the column headers first if they are not already set*/
		if( ! $this->columnHeaders ){
			$this->parseColumnHeaders();
		}
		$rowElements = $this->tbody->childNodes; //DOMNodeList
		/*This will skip row 0 because that is the column headers*/
		for($i=1; $i<$rowElements->length; $i++){ //Loop through each table row
			$currentRow = $rowElements->item($i);
			if( $currentRow->tagName == "tr"){ //Check that we actually have a tr
				$rowNum = $i - 1; //To keep the rows 0-based
				$this->rows[$rowNum]= array();
				$colNum = 0;
				for($j=0; $j < $currentRow->childNodes->length; $j++ ){ //Loop through each cell in a row
					$cell = $currentRow->childNodes->item($j);
					if(isset($cell->tagName)){
						if($cell->tagName == "th" OR $cell->tagName == "td"){ //check that we have a valid table cell
							$this->rows[$rowNum][$this->columnHeaders[$colNum]] = $cell->textContent;
							$colNum++;
						}
					}
				}
				$this->rowCount += 1;
			}
			
		}
	}
	
	
}

?>

