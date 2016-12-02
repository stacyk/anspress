<?php
/**
 * AnsPress comments handling.
 *
 * @author    Rahul Aryan <support@anspress.io>
 * @license   GPL-3.0+
 *
 * @link      https://anspress.io
 *
 * @copyright 2014 Rahul Aryan
 * @package AnsPress/theme
 */

/**
 * Comments class
 */
class AnsPress_Comment_Hooks {

	/**
	 * Comment data.
	 *
	 * @param integer $post_id Post ID.
	 * @param boolean $editing Editing mode.
	 * @return array
	 */
	public static function comments_data( $post_id, $editing = false ) {
		$data = array(
			'current_user_avatar' => get_avatar( get_current_user_id(), 30 ),
		);

		$comments = get_comments( [ 'post_id' => $post_id, 'order' => 'ASC' ] );
		$comments_arr = array();

		foreach ( (array) $comments as $c ) {
			$comments_arr[] = array(
				'ID'        => $c->comment_ID,
				'avatar'    => get_avatar( $c->user_id, 30 ),
				'user_link' => ap_user_link( $c->user_id ),
				'user_name' => ap_user_display_name( $c->user_id ),
				'iso_date'  => date( 'c', strtotime( $c->comment_date ) ),
				'time'      => ap_human_time( $c->comment_date_gmt, false ),
				'content'   => $c->comment_content,
				'approved'  => $c->comment_approved,
				'class'     => implode( ' ', get_comment_class( 'ap-comment', $c->comment_ID, null, false ) ),
				'actions' 	=> ap_comment_actions( $c ),
			);
		}

		if ( ! empty( $comments_arr ) ) {
			return $comments_arr;
		}

		return [];
	}
	/**
	 * Ajax callback for loading comments.
	 *
	 * @since 2.0.1
	 * @since 3.0.0 Moved from AnsPress_Ajax class.
	 */
	public static function load_comments() {
		$post_id = ap_sanitize_unslash( 'post_id', 'r' );

		if ( ! check_ajax_referer( 'comment_form_nonce', '__nonce', false ) ) {
			ap_ajax_json( array(
				'success' => false,
				'snackbar' => [ 'message' => __( 'Unable to load comments', 'anspress-question-answer' ) ],
			));
		}

		$result = array(
			'success'  => true,
			'action'   => 'load_comment_form',
			'comments' => SELF::comments_data( $post_id ),
		);
		ap_ajax_json( $result );
	}

	public static function edit_comment_form() {
		if ( ! is_user_logged_in() || ! ap_verify_nonce( 'comment_form_nonce' ) ) {
			ap_ajax_json( 'something_wrong' );
		}

		$comment_ID = ap_sanitize_unslash('comment_ID', 'request' );

		// Check if user can edit comment.
		if ( ! ap_user_can_edit_comment( $comment_ID, get_current_user_id() ) ) {
			ap_ajax_json( 'no_permission' );
		}

		$comment = get_comment( $comment_ID );

	    $result = array(
			'ap_responce' => true,
			'action' => 'load_comment_form',
			'template' => 'comments',
		);

		$result['key'] = $comment->comment_post_ID.'Comments';

		$result['apData'] = SELF::comments_data( $comment->comment_post_ID, $comment_ID );
		ap_ajax_json( $result );
	}

	/**
	 * Process new comment submission.
	 *
	 * @since 3.0.0
	 */
	public static function new_comment() {
		$post_id = (int) ap_sanitize_unslash( 'post_id', 'r' );
		$content = ap_sanitize_unslash( 'content', 'r' );

		if ( ! is_user_logged_in() || ! ap_verify_nonce( 'new-comment' ) || ! ap_user_can_comment( $post_id ) ) {
			ap_ajax_json( array(
				'success'  => false,
				'snackbar' => [ 'message' => __( 'Unable to post comment', 'anspress-question-answer' ) ],
			) );
		}

		// Check if comment content is not empty.
		if ( empty( $content ) ) {
			ap_ajax_json( array(
				'success'  => false,
				'snackbar' => [ 'message' => __( 'Sorry, you cannot post a blank comment', 'anspress-question-answer' ) ],
			) );
		}

		$_post = ap_get_post( $post_id );

		$type = 'question' === $_post->post_type ? __( 'question', 'anspress-question-answer' ) : __( 'answer', 'anspress-question-answer' );

		// Check if not restricted post type.
		if ( in_array( $_post->post_status, [ 'draft', 'pending', 'trash' ], true ) ) {
			ap_ajax_json( array(
				'success'  => false,
				'snackbar' => [ 'message' => sprintf( __( 'Commenting is not allowed on draft, pending or deleted %s', 'anspress-question-answer' ), $type ) ],
			) );
		}

		// Get current user object.
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			ap_ajax_json( array(
				'success'  => false,
				'snackbar' => [ 'message' => __( 'Sorry, you cannot post a comment', 'anspress-question-answer' ) ],
			) );
		}

		$commentdata = array(
			'comment_post_ID' 		   => $_post->ID,
			'comment_author' 		     => wp_slash( $user->display_name ),
			'comment_author_email' 	 => wp_slash( $user->user_email ),
			'comment_author_url' 	   => wp_slash( $user->user_url ),
			'comment_content' 		   => trim( $content ),
			'comment_type' 			     => 'anspress',
			'comment_parent' 		     => 0,
			'user_id' 				       => $user->ID,
		);

		/**
		 * Filter comment content before inserting to DB.
		 *
		 * @param bool 		$apply_filter 	Apply this filter.
		 * @param string 	$content 		Un-filtered comment content.
		 */
		$commentdata = apply_filters( 'ap_pre_insert_comment', $commentdata );

		// Insert new comment and get the comment ID.
		$comment_id = wp_new_comment( $commentdata );

		if ( false !== $comment_id ) {
			$c = get_comment( $comment_id );
			do_action( 'ap_after_new_comment', $c );

			$count = get_comment_count( $c->comment_post_ID );

			$result = array(
				'success'    => true,
				'comment'      => array(
					'ID'        => $c->comment_ID,
					'avatar'    => get_avatar( $c->user_id, 30 ),
					'user_link' => ap_user_link( $c->user_id ),
					'user_name' => ap_user_display_name( $c->user_id ),
					'iso_date'  => date( 'c', strtotime( $c->comment_date ) ),
					'time'      => ap_human_time( $c->comment_date_gmt, false ),
					'content'   => $c->comment_content,
					'approved'  => $c->comment_approved,
					'class'     => implode( ' ', get_comment_class( 'ap-comment', $c->comment_ID, null, false ) ),
					'actions' 	=> ap_comment_actions( $c ),
				),
				'action' 		  => 'new-comment',
				'commentsCount' => [ 'text' => sprintf( _n( '%d Comment', '%d Comments', $count['all'], 'anspress-question-answer' ), $count['all'] ), 'number' => $count['all'] ],
				'snackbar'   => [ 'message' => __( 'Comment successfully posted', 'anspress-question-answer' ) ],
			);

			ap_ajax_json( $result );
		}
	}

	/**
	 * Updates comment.
	 * @since 3.0.0
	 */
	public static function update_comment( $post_id, $comment_ID, $comment_content ) {
		$comment = get_comment( $comment_ID );
		$content_changed = $comment_content != $comment->comment_content;

		if ( ! $content_changed ) {
			ap_ajax_json( [
				'message'      => __( 'Nothing changed!', 'anspress-question-answer' ),
				'message_type' => 'warning',
			] );
		}

		$updated = wp_update_comment( array(
			'comment_ID'      => $comment_ID,
			'comment_content' => $comment_content,
		) );

		if ( $updated ) {

			$result = array(
				'action' 			=> 'edit_comment',
				'comment_ID' 		=> $comment->comment_ID,
				'comment_post_ID' 	=> $comment->comment_post_ID,
				'message' 			=> 'comment_edit_success',
			);

			$result['key'] = $comment->comment_post_ID.'Comments';

			$result['apData'] = SELF::comments_data( $comment->comment_post_ID );
			ap_ajax_json( $result );
		}
	}

	/**
	 * Ajax action for deleting comment.
	 *
	 * @since 2.0.0
	 * @since 3.0.0 Moved from ajax.php to here.
	 */
	public static function delete_comment() {
		$comment_id = (int) ap_sanitize_unslash( 'comment_ID', 'r' );

		if ( ! $comment_id || ! ap_user_can_delete_comment( $comment_id ) || ! ap_verify_nonce( 'delete_comment' ) ) {
			ap_ajax_json( array(
				'success' => false,
				'snackbar' => [ 'message' => __( 'Failed to delete comment', 'anspress-question-answer' ) ],
			) );
		}

		$_comment = get_comment( $comment_id );

		// Check if deleting comment is locked.
		if ( ap_comment_delete_locked( $_comment->comment_ID ) && ! is_super_admin() ) {
			ap_ajax_json( array(
				'success' => false,
				'snackbar' => [ 'message' => sprintf( __( 'This comment was created %s. Its locked hence you cannot delete it.', 'anspress-question-answer' ), ap_human_time( $_comment->comment_date_gmt, false ) ) ],
			) );
		}

		$delete = wp_delete_comment( (integer) $_comment->comment_ID, true );

		if ( $delete ) {
			do_action( 'ap_unpublish_comment', $_comment );
			do_action( 'ap_after_deleting_comment', $_comment );

			$count = get_comments_number( $_comment->comment_post_ID );

			ap_ajax_json( array(
				'success'       => true,
				'snackbar'      => [ 'message' => __( 'Comment successfully deleted', 'anspress-question-answer' ) ],
				'action'        => 'delete_comment',
				'commentsCount' => [ 'text' => sprintf( _n( '%d Comment', '%d Comments', $count, 'anspress-question-answer' ), $count ), 'number' => $count ],
			) );
		}
	}

	/**
	 * Modify comment query args for showing pending comments to moderator.
	 * @param  array $args Comment args.
	 * @return array
	 * @since  3.0.0
	 */
	public static function comments_template_query_args( $args ) {
		if ( ap_user_can_approve_comment() ) {
			$args['status'] = 'all';
		}
		return $args;
	}

	/**
	 * Ajax callback to approve comment.
	 */
	public static function approve_comment() {
		$comment_id = (int) ap_sanitize_unslash( 'comment_ID', 'r' );

		if ( ! ap_verify_nonce( 'approve_comment_' . $comment_id ) || ! ap_user_can_approve_comment( ) ) {
			ap_ajax_json( array(
				'success'  => false,
				'snackbar' => [ 'message' => __( 'Sorry, unable to approve comment', 'anspress-question-answer' ) ],
			) );
		}

		$success = wp_set_comment_status( $comment_id, 'approve' );
		$_comment = get_comment( $comment_id );
		$count = get_comments_number( $_comment->comment_post_ID );

		if ( $success ) {
			ap_ajax_json( array(
				'success'      => true,
				'action' 		   => 'comment_approved',
				'model'      	 => [ 'approved' => '1', 'actions' => ap_comment_actions( $c ) ],
				'comment_ID' 	 => $comment_id,
				'commentsCount' => [ 'text' => sprintf( _n( '%d Comment', '%d Comments', $count, 'anspress-question-answer' ), $count ), 'number' => $count ],
				'snackbar'     => [ 'message' => __( 'Comment approved successfully.', 'anspress-question-answer' ) ],
			) );
		}
	}
}

/**
 * Load comment form button.
 *
 * @param 	bool $echo Echo html.
 * @return 	string
 * @since 	0.1
 */
function ap_comment_btn_html( $_post = null ) {
	$_post = ap_get_post( $_post );

	if ( 'question' === $_post->post_type  && ap_opt( 'disable_comments_on_question' ) ) {
		return;
	}

	if ( 'answer' === $_post->post_type && ap_opt( 'disable_comments_on_answer' ) ) {
		return;
	}

	$comment_count = get_comments_number( $_post->ID );
	$args = wp_json_encode( [ 'post_id' => $_post->ID, '__nonce' => wp_create_nonce( 'comment_form_nonce' ) ] );

	$q = wp_json_encode( array(
		'post_id'        => get_the_ID(),
		'__nonce'        => wp_create_nonce( 'new-comment' ),
		'ap_ajax_action' => 'new_comment',
	) );

	$unapproved = '';
	if( !empty( $_post->fields['unapproved_comments'] ) ) {
		$unapproved = '<b class="unapproved">' . $_post->fields['unapproved_comments'] . '</b>';
	}

	$output = '<a href="#comments-' . $_post->ID . '" class="ap-btn ap-btn-comments" ap="comment_btn" ap-query="' . esc_js( $args ) . '" ap-commentscount-text>' . sprintf( _n( '%d Comment', '%d Comments', $comment_count, 'anspress-question-answer' ), $comment_count ) . $unapproved . '</a><a href="#" class="ap-btn-newcomment ap-btn ap-btn-small" ap="new-comment" ap-query="' . esc_js( $q ) . '">' . esc_attr__( 'Add a Comment', 'anspress-question-answer' ) . '</a>';

	return $output;
}

/**
 * Comment actions args.
 *
 * @param object|integer $comment Comment object.
 * @return array
 * @since 4.0.0
 */
function ap_comment_actions( $comment ) {
	$comment = get_comment( $comment );
	$actions = [];

	if ( ap_user_can_edit_comment( $comment->comment_ID ) ) {
		$actions[] = [ 'label' => __( 'Edit', 'anspress-question-answer' ), 'query' => [ '__nonce' => wp_create_nonce( 'comment_form_nonce' ), 'comment_ID' => $comment->comment_ID, 'ap_ajax_action' => 'edit_comment_form' ] ];
	}

	if ( ap_user_can_delete_comment( $comment->comment_ID ) ) {
		$actions[] = [ 'label' => __( 'Delete', 'anspress-question-answer' ), 'query' => [ '__nonce' => wp_create_nonce( 'delete_comment' ), 'comment_ID' => $comment->comment_ID, 'ap_ajax_action' => 'delete_comment' ] ];
	}

	if ( '0' === $comment->comment_approved && ap_user_can_approve_comment( ) ) {
		$actions[] = [ 'label' => __( 'Approve', 'anspress-question-answer' ), 'query' => [ '__nonce' => wp_create_nonce( 'approve_comment_' . $comment->comment_ID ), 'comment_ID' => $comment->comment_ID, 'ap_ajax_action' => 'approve_comment' ] ];
	}

	/**
	 * For filtering comment action buttons.
	 *
	 * @param array $actions Comment actions.
	 * @since   2.0.0
	 */
	return apply_filters( 'ap_comment_actions', $actions );
}

/**
 * Check if comment delete is locked.
 * @param  integer $comment_ID     Comment ID.
 * @return bool
 * @since  3.0.0
 */
function ap_comment_delete_locked( $comment_ID ) {
	$comment = get_comment( $comment_ID );
	$commment_time = mysql2date( 'U', $comment->comment_date_gmt ) + (int) ap_opt( 'disable_delete_after' );
	return current_time( 'timestamp', true ) > $commment_time;
}

