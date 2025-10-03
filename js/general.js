function bh_create_zip(postID) {
    // Disables the download button and shows loading state
    jQuery('.bh_download_button').attr('disabled', '').addClass('bh_download_button_loading');

    jQuery.ajax({
        type: 'post',
        async: true,
        url: bh_ajax.ajax_url, // AJAX URL localized from PHP
        data: {
            action: 'bh_create_zip_file',
            postId: postID
        },
        success: function(data) {
            // Re-enable button and remove loading state
            jQuery('.bh_download_button').removeAttr('disabled').removeClass('bh_download_button_loading');

            if (data.success) {
                // Redirect to the generated ZIP file
                window.location = data.data;
            } else {
                // Show an error if something went wrong
                alert('Error: ' + data.data);
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            jQuery('.bh_download_button').removeAttr('disabled').removeClass('bh_download_button_loading');
            alert('AJAX request failed: ' + textStatus + ' - ' + errorThrown);
        }
    });
}
