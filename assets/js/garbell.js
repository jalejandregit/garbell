jQuery(document).ready(function($){


    $(document).ready(function() {
        if (window.location.href.includes("garbell-contributor")) {
            $("#myLoaderGif").hide();
            

        }
    });



    $("#garbell-run").on('click', function(){
        $("#myLoaderGif").show();
    });


    //$.get("garbell-contributor", function(data){
    ///        $("#myLoaderGif").hide();
     //   });


    $('#garbell-run').on('click', function(e){
        e.preventDefault();
        
        
        var data = {
            action: 'garbell_run_scrape',
            nonce: garbell_ajax.nonce,
            startUrl: $('#startUrl').val(),
            maxDepth: $('#maxDepth').val(),
            maxPages: $('#maxPages').val(),
            selectorPages: $('#selectorPages').val(),
            selectorEntries: $('#selectorEntries').val(),
            tagsExclusions: $('#tagsExclusions').val(),
            selectorImages: $('#selectorImages').val()
        };
        $('#garbell-results').html('<p>  Executant sraping a '+ $('#startUrl').val() +'... (pot trigar uns segons)</p>');
        $.post(garbell_ajax.ajax_url, data, function(resp){
            if (!resp.success) {
                $('#garbell-results').html('<div class="error"><p>'+resp.data+'</p></div>');
                return;
            }
            renderResults(resp.data);
        });
    });

    function renderResults(data) {
        // data.postsData is array of extracted posts
        var html = '';
        if (!data.postsData || data.postsData.length === 0) {
            html = '<p>No se han encontrado posts válidos per' + $('#startUrl').val() + '.</p>';
            $('#garbell-results').html(html);
            return;
        }

        html += '<form id="garbell-import-form">';
        html += '<table class="garbell fixed" id="garbell-table"><thead><tr>';
        html += '<th style="width: 4%;">Selecció</th>';
        html += '<th style="width: 42%;">TitlePost</th>';
        html += '<th style="width: 20%;">LinkPost</th>';
        html += '<th style="width: 4%;">Orientació</th>';
        html += '<th style="width: 20%;">Coordenades</th>';
        html += '<th style="width: 10%;">Autor</th>';
        html += '</tr></thead><tbody>';
        data.postsData.forEach(function(post, idx){
            var id = 'p_' + idx;
            html += '<tr data-idx="'+idx+'">';
            html += '<td style="width: 4%;"><input type="checkbox" class="g-select" data-idx="'+idx+'"></td>';
            html += '<td style="width: 32%;">'+escapeHtml(post.linkTitlePost || '')+'</td>';
            html += '<td style="width: 20%;"><a href="'+post.linkPost+'" target="_blank">'+post.linkPost+'</a></td>';
            html += '<td style="width: 4%;"><select class="g-orient"><option value="">-</option><option>N</option><option>NE</option><option>E</option><option>SE</option><option>S</option><option>SW</option><option>W</option><option>NW</option></select></td>';
            html += '<td style="width: 20%;"><input type="text" class="g-coordenadas" style="width:300px;"></td>';
            //html += '<td style="width: 10%;">'+escapeHtml(post.linkAutor || '')+'</td>';
            html += '<td style="width: 10%;"><a href="'+post.linkAutorUrl+'" target="_blank">'+post.linkAutor+'</a></td>';
            html += '</tr>';

            // nested images table
            html += '<tr class="g-images-row"><td colspan="6">';
            html += '<table class="garbell nested"><thead><tr>';
            html += '<th style="width: 5%;">Seleccionar</th>';
            html += '<th style="width: 10%;">Thumbnail</th>';
            html += '<th style="width: 85%;">UrlImageLarge</th></tr></thead><tbody>';
            var maxImgs = Math.max(post.linksImgThumb.length, post.linksImg.length);
            for (var i=0;i<maxImgs;i++) {
                var thumb = post.linksImgThumb[i] || '';
                var full = post.linksImg[i] || '';
                html += '<tr>';
                html += '<td style="width: 5%;"><input type="checkbox" class="g-img-select" data-pidx="'+idx+'" data-imgidx="'+i+'"></td>';
                html += '<td style="width: 10%;">' + (thumb ? '<img src="'+thumb+'" style="max-width:150px;display:block;"><div><input type="hidden" class="g-thumb-url" value="'+thumb+'" style="width:80%"></div>' : '') + '</td>';
                html += '<td style="width: 85%;">' + (full ? '<a href="'+full+'" target="_blank">'+full+'</a><div><input type="hidden" class="g-full-url" value="'+full+'" style="width:80%"></div>' : '') + '</td>';
                html += '</tr>';
            }
            html += '</tbody></table></br>';
            html += '</td></tr>';
        });
        html += '</tbody></table>';
        html += '<div>';
        html += '<p><button id="garbell-import" type="button" class="button button-primary">Importar seleccionats</button></p>';
        html += '<img id="myLoaderGifImport" style="display: none;" src="http://localhost/triapedres/wp-content/uploads/2025/11/lBsSZ.gif" alt="GIF de carga AJAX"></div>';
        html += '</form>';
        $('#garbell-results').html(html);
        //$("myLoaderGifImport").hide();
        $('#garbell-import').on('click', function(){

            //$("#myLoaderGifImport").show();//mostrem el gif de carga en començar la importació
            $("#myLoaderGifImport").toggle();

            // build payload
            var payload = [];
            $('#garbell-table tbody tr').each(function(){
                var tr = $(this);
                var idx = tr.data('idx');
                if (typeof idx === 'undefined') return;
                var checked = tr.find('.g-select').is(':checked');
                if (!checked) return;
                var post = data.postsData[idx];
                var lat = ''; //tr.find('.g-lat').val();
                var lon = ''; //tr.find('.g-lon').val();
                var coords = tr.find('.g-coordenadas').val();
                if (coords.trim().length === 0) {
                    alert("El camp Coordenades és buit, completar abans d'importar.");
                return false;
                }

                if (coords) {
                    // parse coords
                    var parts = coords.split(',');
                    if (parts.length === 2) {
                        lat = parts[0].trim();
                        lon = parts[1].trim();
                    }
                }


                var orient = tr.find('.g-orient').val();
                //alert("Orientació seleccionada: " + orient);
                // collect selected images
                var images = [];
                var thumbs = [];
                // find nested rows
                $('input.g-img-select[data-pidx="'+idx+'"]').each(function(){
                    var imgIdx = $(this).data('imgidx');
                    if (!$(this).is(':checked')) return;
                    var thumbVal = $('input.g-thumb-url').eq(imgIdx + (idx*0)).val(); // index mapping safe because inputs are in order
                    // better: search exact inputs near this checkbox
                    var row = $(this).closest('tr');
                    var thumbVal = row.find('.g-thumb-url').val();
                    var fullVal = row.find('.g-full-url').val();
                    if (fullVal) images.push(fullVal);
                    if (thumbVal) thumbs.push(thumbVal);
                });

                payload.push({
                    linkPost: post.linkPost,
                    linkTitlePost: post.linkTitlePost,
                    linkContent: post.linkContent,
                    linksImg: images,
                    linksImgThumb: thumbs,
                    lat: lat,
                    lon: lon,
                    autor: post.linkAutor,
                    autorUrl: post.linkAutorUrl,
                    orientacio: orient,
                    location: ''
                });
            });

            if (payload.length === 0) {
                alert('No hay posts seleccionados para importar.');
                return;
            }

            var send = {
                action: 'garbell_import_selected',
                nonce: garbell_ajax.nonce,
                payload: JSON.stringify(payload)
            };
            $.post(garbell_ajax.ajax_url, send, function(resp){
                if (!resp.success) {
                    alert('Error: ' + resp.data);
                    return;
                }
                $("#myLoaderGifImport").toggle();
                alert('Importado. IDs: ' + JSON.stringify(resp.data.inserted_post_ids));
            });
            //$("#myLoaderGifImport").hide();//ocultem el gif de carga en acabar la importació  
        });

        $("#myLoaderGif").hide();//ocultem el gif de carga en acabar l'scraping
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/[&<>"'\/]/g, function (s) {
            var entityMap = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': '&quot;', "'": '&#39;', "/": '&#x2F;' };
            return entityMap[s];
        });
    }

    function stripTags(html) {
        return html.replace(/(<([^>]+)>)/ig,"");
    }
});
