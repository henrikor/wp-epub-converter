jQuery(document).ready(function($) {
    var modal = $('#epubModal');
    var span = $('.close');

    $('.epub-link').click(function(e) {
        e.preventDefault();
        var postId = $(this).data('post-id');
        $('#epubPostId').val(postId);

        // Set default author
        $('#epubAuthor').val(wpEpubConverter.default_author);

        // Fetch post title and set default title
        $.get(wpEpubConverter.ajax_url, {
            action: 'get_post_title',
            post_id: postId
        }, function(response) {
            if (response.success) {
                var title = response.data.title;
                $('#epubTitle').val(title);
            } else {
                console.error('Failed to fetch post title:', response.data.message);
            }
        });

        // Set default EPUB version to 3
        $('#epubVersion').val(3);

        modal.show();
    });

    span.click(function() {
        modal.hide();
    });

    $(window).click(function(event) {
        if ($(event.target).is(modal)) {
            modal.hide();
        }
    });

    $('#epubForm').submit(function(e) {
        e.preventDefault();

        var data = {
            action: 'generate_epub',
            post_id: $('#epubPostId').val(),
            author: $('#epubAuthor').val(),
            title: $('#epubTitle').val(),
            version: $('#epubVersion').val(),
            kepub: $('#epubKepub').is(':checked') ? 1 : 0
        };

        console.log('Submitting form with data:', data);

        $.post(wpEpubConverter.ajax_url, data, function(response) {
            console.log('AJAX response:', response);

            if (response.success) {
                var downloadLink = '<a href="' + response.data.url + '">Download EPUB</a>';
                $('#downloadLink').remove(); // Remove any existing download link
                $('#epubForm').append('<div id="downloadLink" style="display:none;">' + downloadLink + '</div>');
                $('#downloadLink').slideDown();
            } else {
                console.error('Failed to generate EPUB:', response.data.message);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX error:', textStatus, errorThrown);
        });
    });
});
