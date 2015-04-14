<?php
/**
 * AnsPress users loop functions
 *
 * Helper functions for AnsPress users loop
 *
 * @package   AnsPress
 * @author    Rahul Aryan <support@anspress.io>
 * @license   GPL-2.0+
 * @link      http://anspress.io
 * @copyright 2014 Rahul Aryan
 */

class AP_Users_Query
{
/**
     * The loop iterator.
     *
     * @access public
     * @var int
     */
    var $current_user = -1;

    /**
     * The number of users returned by the paged query.
     *
     * @access public
     * @var int
     */
    var $user_count;

    /**
     * Array of users located by the query.
     *
     * @access public
     * @var array
     */
    var $users;

    /**
     * The user object currently being iterated on.
     *
     * @access public
     * @var object
     */
    var $user;

    /**
     * A flag for whether the loop is currently being iterated.
     *
     * @access public
     * @var bool
     */
    var $in_the_loop;

    /**
     * The total number of users matching the query parameters.
     *
     * @access public
     * @var int
     */
    var $total_user_count;

    /**
     * Items to show per page
     *
     * @access public
     * @var int
     */
    var $per_page;
    var $total_page = 1;
    var $paged;
    var $offset;


    function __construct($args = '')
    {
        $count_args = array(
            'fields' => 'all_with_meta',
            'number' => 999999
        );

        $user_count_query = new WP_User_Query($count_args);
        $user_count = $user_count_query->get_results();
        
        // count the number of users found in the query
        $this->total_user_count = $user_count ? count($user_count) : 1;

        $this->per_page = ap_opt('users_per_page');

       
        // grab the current page number and set to 1 if no page number is set
        $this->paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
    
        $this->offset = $this->per_page * ($this->paged - 1);


        $this->total_pages = ceil($this->total_user_count / $this->per_page);

        $args =  wp_parse_args( $args, array(
            'number' => $this->per_page,
            'offset' => $this->offset
        ));

        if(isset($args['sortby']) && $args['sortby'] == 'points')
        {
            $args['ap_query']   = 'sort_points';
            $args['meta_key']   = 'ap_reputation';
            $args['orderby']    = 'meta_value';
            $args['order']      = 'DESC';
        }
        elseif(isset($args['sortby']) && $args['sortby'] == 'newest')
        {
            $args['orderby']    = 'date';
            $args['order']      = 'DESC';
        }

        $users_query = new WP_User_Query( $args );

        $this->users = $users_query->results;

        $this->user_count = count($this->users);
    }

    public function users()
    {
        if ( $this->current_user + 1 < $this->user_count ) {
            return true;
        } elseif ( $this->current_user + 1 == $this->user_count ) {

            do_action('ap_user_loop_end');
            // Do some cleaning up after the loop
            $this->rewind_users();
        }

        $this->in_the_loop = false;
        return false;
    }

    /**
     * Check if there are users in loop
     *
     * @return bool
     */
   public  function has_users() {
        if ( $this->user_count )
            return true;

        return false;
    }

    /**
     * Set up the next user and iterate index.
     *
     * @return object The next user to iterate over.
     */
    public function next_user() {
        $this->current_user++;
        $this->user = $this->users[$this->current_user];

        return $this->user;
    }

    /**
     * Rewind the users and reset user index.
     */
    public function rewind_users() {
        $this->current_user = -1;
        if ( $this->user_count > 0 ) {
            $this->user = $this->users[0];
        }
    }

    /**
     * Set up the current user inside the loop.
     */
    public function the_user() {

        $this->in_the_loop = true;
        $this->user      = $this->next_user();

        // loop has just started
        if ( 0 == $this->current_user ) {

            /**
             * Fires if the current user is the first in the loop.
             */
            do_action( 'ap_user_loop_start' );
        }

    }
}


function ap_has_users($args = ''){
    global $users_query;
    $sort = get_query_var('ap_sort');
    $users_query = new AP_Users_Query($args);

    return $users_query->has_users();
}

function ap_users(){
    global $users_query;    
    return $users_query->users();
}

function ap_the_user(){
    global $users_query;     
    return $users_query->the_user();  
}

function ap_user_the_object(){
    global $users_query; 
    $user = $users_query->user;

    return $user;
}

/**
 * Echo active user ID
 */
function ap_user_the_ID(){
    echo ap_user_get_the_ID();
}

    /**
     * Return memeber ID active in loop
     * @return integer
     */
    function ap_user_get_the_ID(){
        global $users_query; 
        $user = $users_query->user;

        return $user->data->ID;
    }

/**
 * Echo active user display name
 */
function ap_user_the_display_name(){
    echo ap_user_get_the_display_name();
}
    /**
     * Return active user ID
     * @return integer
     */
    function ap_user_get_the_display_name(){
        return ap_user_display_name(array('user_id' => ap_user_get_the_ID()));
    }

/**
 * echo active user link
 */
function ap_user_the_link(){
    echo ap_user_get_the_link();
}
    /**
     * Retrive active user link
     * @return string Link to user profile
     */
    function ap_user_get_the_link(){
        return ap_user_link(ap_user_get_the_ID());
    }

/**
 * Echo active user avatar
 */
function ap_user_the_avatar($size = 40){
    echo ap_user_get_the_avatar($size);
}

    /**
     * Retrive active user avatar
     * @param  integer $size height and width of avatar
     * @return string return avatar <img> tag
     */
    function ap_user_get_the_avatar($size = 40){
        if(is_ap_users() && 40 == $size)
            $size = ap_opt('users_page_avatar_size');
        
        return get_avatar( ap_user_get_the_ID(), $size );
    }

/**
 * Echo active user reputation
 * @param  boolean $short Shorten count like 2.8k
 */
function ap_user_the_reputation($short = true){
    echo ap_user_get_the_reputation($short);
}
    
    /**
     * Get active user reputation
     * @param  boolean $short Shorten count like 2.8k
     * @return string
     */
    function ap_user_get_the_reputation($short = true){
        return ap_get_reputation(ap_user_get_the_ID(), $short);
    }

/**
 * output users page pagination
 * @return string pagination html tag
 */
function ap_users_the_pagination(){
    global $users_query;

    $base = ap_get_link_to('users') . '/%_%';
    $user = $users_query->user;

    ap_pagination($users_query->paged, $users_query->total_pages, $base);
}