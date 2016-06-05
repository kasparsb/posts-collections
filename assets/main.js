;jQuery(function($){
    var $list = $('ol.postscollections__posts');
    $list.sortable({
        update: function() {

            var r = [];
            $list.find('li').each(function(index){
                r.push({
                    post_id: $(this).find('[name=post_id]').val(),
                    collection: $(this).find('[name=collection]').val()
                })
            });

            $.post(postscollections.ajax_url, {
                action: 'postscollectionssaveorder',
                order: r
            })
        }
    });

    $list.on('click', '.postscollections__remove', function(ev){
        ev.preventDefault();

        var $li = $(this).parents('li');
        $.post(postscollections.ajax_url, {
            action: 'postscollectionsremove',
            item: {
                post_id: $li.find('[name=post_id]').val(),
                collection: $li.find('[name=collection]').val()
            }
        }, function(){
            window.location = window.location;
        });
        
    });
    
});