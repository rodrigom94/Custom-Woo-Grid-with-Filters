jQuery(document).ready(function($){
    console.log("scripts.js loaded 2");

    function showLoader() {
        var $productosContainer = $('#productos-grillawoo .productos-container');
        $productosContainer.css('position', 'relative');
        var loaderHTML = `
            <div class="loader">
                <div class="spinner"></div>
            </div>
        `;
        $productosContainer.append(loaderHTML);
    }

    function hideLoader() {
        $('.loader').remove();
    }

    function fetch_products(page){
        var categorias = [];
        $('#filtro-categoria input[type=checkbox]:checked').each(function() {
            categorias.push($(this).val());
        });

        var atributos = [];
        $('.isFilterRd input[type=checkbox]:checked').each(function() {
            var $parentUl = $(this).closest('ul');
            if ($parentUl.attr('id') && !$parentUl.is('#filtro-categoria')) {
                var taxonomy = $parentUl.attr('id').replace('filtro-', '');
                if (!atributos[taxonomy]) {
                    atributos[taxonomy] = [];
                }
                atributos[taxonomy].push($(this).val());
            }
        });
        console.log("categorias", categorias);
        console.log("atributos", atributos);

        var cwrProductId = new URLSearchParams(window.location.search).get('cwr_product_id');

        var $productosGrid = $('#productos-grillawoo');
        var $productosContainer = $productosGrid.find('.productos-container');
        var initialHeight = $productosContainer.height();

        showLoader();

        $.ajax({
            url: my_ajax_object.ajax_url,
            type: 'post',
            data: {
                action: 'grillawoo_ajax_pagination',
                page: page,
                busqueda: "",
                categorias: categorias,
                atributos: atributos,
                cwr_product_id: cwrProductId,
                ajax: 1
            },
            beforeSend: function() {
                $productosContainer.css({
                    'height': initialHeight + 'px',
                    'overflow': 'hidden'
                }).animate({
                    'opacity': 0
                }, 100);
            },
            success: function(response){
                var $newContent = $('<div />').html(response.productos_html);

                $productosContainer.html($newContent.find('.productos-container').html()).css('opacity', 0).animate({
                    'opacity': 1
                }, 100, function(){
                    $(this).css({
                        'height': 'auto',
                        'overflow': 'visible'
                    });
                });

                hideLoader();

                $('.pagination').html(response.pagination);
                $('.pagination a').removeClass('active');
                $('.pagination a[data-page="' + page + '"]').addClass('active');

                // Scroll to the top of the grid
                $('html, body').animate({
                    scrollTop: $('#productos-grillawoo').offset().top - 20
                }, 500);

                console.log(response.query); // Mostrar la consulta en la consola
            }
        });
    }

    function cargarProductosConFiltros(page = 1) {
        fetch_products(page);
    }

    $(document).on('click', '.pagination a', function(e){
        e.preventDefault();
        var page = $(this).data('page');
        fetch_products(page);
    });

    $('.isFilterRd li *').on('click', function(e) {
        if(e.target.nodeName == "INPUT"){
            cargarProductosConFiltros();
        }
    });

    $('#clear-filters').on('click', function(e) {
        e.preventDefault();
        $('.isFilterRd input[type=checkbox]').prop('checked', false);
        cargarProductosConFiltros();
    });


    
    // Debouncing function
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    // Buscador
    const searchProducts = debounce(function(query) {
        var resultsContainer = $('.buttonContainer__buscar__input').siblings('.search-results');
        if (query.length > 0) {
            $.ajax({
                url: my_ajax_object.ajax_url,
                type: 'GET',
                data: {
                    action: 'search_products',
                    query: query
                },
                success: function(response) {
                    resultsContainer.html(response);
                    resultsContainer.show();
                }
            });
        } else {
            resultsContainer.hide();
        }
    }, 1000); // 1 segundo de espera

    // Función para manejar la descripción emergente usando delegación de eventos
    function handleProductDescription() {
        $('.productos-container').on('mouseenter', '.producto', function() {
            $(this).find('.producto-descripcion').fadeIn(200);
        }).on('mouseleave', '.producto', function() {
            $(this).find('.producto-descripcion').fadeOut(200);
        });
    }

    // Llamar a handleProductDescription una sola vez al inicio
    handleProductDescription();

    // No es necesario llamar a handleProductDescription después de cada actualización
    function cargarProductosConFiltros(page = 1) {
        fetch_products(page);
    }

    var player;

    // Función para cargar la API de YouTube
    function loadYouTubeAPI() {
        var tag = document.createElement('script');
        tag.src = "https://www.youtube.com/iframe_api";
        var firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
    }

    // Función llamada por la API de YouTube cuando está lista
    window.onYouTubeIframeAPIReady = function() {
        player = new YT.Player('youtube-player', {
            events: {
                'onReady': onPlayerReady
            }
        });
    }

    function onPlayerReady(event) {
        // El reproductor está listo
    }

    // Función para mostrar el video de YouTube
    function showYoutubeVideo() {
        $('#youtube-video-container').css('display', 'flex');
        $('#productos-grillawoo').addClass('video-active');
    }

    // Cerrar el video de YouTube
    $('#close-youtube-video').on('click', function() {
        $('#youtube-video-container').hide();
        $('#productos-grillawoo').removeClass('video-active');
        if (player && typeof player.stopVideo === 'function') {
            player.stopVideo();
        }
    });

    // Mostrar el video al cargar la página
    showYoutubeVideo();

    // Cargar la API de YouTube
    loadYouTubeAPI();

    $('.buttonContainer__buscar__input').on('keyup', function() {
        var query = $(this).val();
        searchProducts(query);
    });

    // Redireccionar cuando se haga clic en un término personalizado
    $(document).on('click', '.search-result-item.custom-term a', function(e) {
        e.preventDefault();
        var link = $(this).attr('href');
        window.location.href = link;
    });

    // Cerrar resultados de búsqueda cuando se hace clic fuera
    $(document).on('click', function(e) {
        if (!$('.search-results').is(e.target) && $('.search-results').has(e.target).length === 0) {
            $('.search-results').hide();
        }
    });
});
