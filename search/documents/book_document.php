<?php
/**
* Global Search Engine for Moodle
*
* @package search
* @category core
* @subpackage document_wrappers
* @author Valery Fremaux [valery.fremaux@club-internet.fr] > 1.9
* @contributor by Paul Kelly [kellyp@dumgal.ac.uk] to allow for searching of the Book Module
* @date 2010/09/10
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
*
*
*/

/**
* requires and includes
*/
require_once("$CFG->dirroot/search/documents/document.php");
require_once("$CFG->dirroot/mod/resource/lib.php");

/* 
*  Global Search API 
*/
class BookSearchDocument extends SearchDocument {
    public function __construct(&$book, $context_id) {
        // generic information; required
        
		 global $CFG;
		 
		$context = get_context_instance_by_id($context_id);
	
		$doc->docid     = $book['id'];
        $doc->documenttype = SEARCH_TYPE_BOOK;
        $doc->itemtype     = 'book';
        $doc->contextid    = $context_id;
		
		
		if (!$book['bookid']){   // then instance is not a chapter
		
		
				
				$doc->title     = strip_tags($book['name']);  // need to check if content is book or chapter
				$doc->date      = $book['timemodified'];
				$doc->author    = '';
				$doc->contents  = strip_tags($book['name']);
				$doc->contents .= strip_tags($book['summary']);
				$doc->url       = $CFG->wwwroot.'/mod/book/view.php?id='. $context->instanceid ;
		
		}else{  // must be a chapter
				
				$doc->title     = strip_tags($book['title']);  
				$doc->date      = $book['timemodified'];
				$doc->author    = '';
				$doc->contents  .= strip_tags($book['title']);
				$doc->contents .= strip_tags($book['content']);
				
				//code to get correct context ID
				$coursemodule = get_field('modules', 'id', 'name', 'book');
				$cm = get_record('course_modules', 'module', $coursemodule, 'instance', $book['bookid']);
				$context = get_context_instance(CONTEXT_MODULE, $cm->id);
				
				$doc->url       = $CFG->wwwroot.'/mod/book/view.php?id='. $cm->id . "&chapterid=" .$book['id'];
	
		
		}
		
		
        $data = array();  // not sure what this is for?
        
        // construct the parent class
        parent::__construct($doc, $data, $book['course'], 0, 0, 'mod/'.SEARCH_TYPE_BOOK);
    } //constructor
}




/**
* part of standard API
*
* pull all data from mdl_book & mdl_bookchapters and joined it for easier iteration
*
*/
function book_iterator() {
    
    $books = get_records('book');
    $books_chapters = get_records('book_chapters');
    $books_joined_contents = array_merge((array)$books, (array)$books_chapters);
    return $books_joined_contents;
}

/**
* part of standard API
* this function does not need a content iterator, returns all the info
* itself;
* @param notneeded to comply API, remember to fake the iterator array though
* @uses CFG
* @return an array of searchable documents
*/
function book_get_content_for_index(&$book) {
    global $CFG;

    // starting with Moodle native resources
    $documents = array();

    $coursemodule = get_field('modules', 'id', 'name', 'book');
    $cm = get_record('course_modules', 'course', $book->course, 'module', $coursemodule, 'instance', $book->id);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    $documents[] = new bookSearchDocument(get_object_vars($book), $context->id);

    mtrace("finished book {$book->id}");
    return $documents;
}

/**
* part of standard API.
* returns a single resource search document based on a book id
* @param id the id of the accessible document
* @return a searchable object or null if failure
*/
function book_single_document($id, $itemtype) {
    global $CFG;
    
    $book = get_record('book', 'id', $id);

    if ($book){
        $coursemodule = get_field('modules', 'id', 'name', 'book');
        $cm = get_record('course_modules', 'id', $book->id);
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        return new bookSearchDocument(get_object_vars($book), $context->id);
    }
    return null;
}

/**
* returns the var names needed to build a sql query for addition/deletions
*
*/
function book_db_names() {
    //[primary id], [table name], [time created field name], [time modified field name], [docsubtype], [additional where conditions for sql]
    return array(array('id', 'book', 'timemodified', 'timemodified', 'book', ''));
}

/**
* this function handles the access policy to contents indexed as searchable documents. If this 
* function does not exist, the search engine assumes access is allowed.
* @param path the access path to the module script code
* @param itemtype the information subclassing (usefull for complex modules, defaults to 'standard')
* @param this_id the item id within the information class denoted by itemtype. In resources, this id 
* points to the resource record and not to the module that shows it.
* @param user the user record denoting the user who searches
* @param group_id the current group used by the user when searching
* @return true if access is allowed, false elsewhere
*/
function book_check_text_access($path, $itemtype, $this_id, $user, $group_id, $context_id){
    global $CFG;
    
    // include_once("{$CFG->dirroot}/{$path}/lib.php");
    
    $r = get_record('book', 'id', $this_id);
    $module_context = get_record('context', 'id', $context_id);
    $cm = get_record('course_modules', 'id', $module_context->instanceid);
    if (empty($cm)) return false; // Shirai 20093005 - MDL19342 - course module might have been delete

    $course_context = get_context_instance(CONTEXT_COURSE, $r->course);

    //check if englobing course is visible
    if (!has_capability('moodle/course:view', $course_context)){
        return false;
    }

    //check if found course module is visible
    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $module_context)){
        return false;
    }
    
    return true;
}

/**
* post processes the url for cleaner output.
* @param string $title
*/
function book_link_post_processing($title){
    global $CFG;
    
    if ($CFG->block_search_utf8dir){
        return mb_convert_encoding("(".shorten_text(clean_text($title), 60)."...) ", 'UTF-8', 'auto');
    }
    return mb_convert_encoding("(".shorten_text(clean_text($title), 60)."...) ", 'auto', 'UTF-8');
}


?>
