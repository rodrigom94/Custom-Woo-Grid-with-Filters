jQuery(document).ready(function($){
    console.log("scripts.js loaded");

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
            if ($parentUl.attr('id') && !$parentUl.is('#filtro-categoria')) { // Excluir categorías
                var taxonomy = $parentUl.attr('id').replace('filtro-', '');
                atributos.push({ [taxonomy]: $(this).val() });
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
});
