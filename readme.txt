garbell (nativa) - Scraper WordPress sin librerías externas

Instalación:
1. Descomprime garbell_native.zip en wp-content/plugins/
2. Activa el plugin desde el panel de WordPress
3. Ajusta opciones en garbell -> Settings
4. Colaboradores usan garbell -> Colaborador para ejecutar scraping, revisar y luego importar

Este paquete es 100% nativo PHP + API de WordPress, no requiere Composer ni vendor/.


garbell/
├── assets/
│   ├── js/
│   │   └── garbell.js
│   └── css/
│       └── garbell.css
├── includes/
│   ├── admin-page.php
|   ├── admin-plantilles-page.php
│   ├── contributor-page.php
│   ├── scraper.php
│   ├── importer.php
│   └── registres-page.php
├── garbell.php
└── readme.md

#---- XPATH ----

---- https://santillop.blogspot.com/
pag --> //a[@id='Blog1_blog-pager-older-link']
exc --> //p[not(.//a[@href])]
post -> //div[@id='Blog1']/div[@class='blog-posts hfeed']/div[@class='date-outer'][*]/div[@class='date-posts']/div[@class='post-outer'][1]/div[@class='post hentry uncustomized-post-template']

--- https://logalldeponent.blogspot.com/
Crear SelectorAnchor --> anchorImgs = $xpath->query("//*[@id='main']//a[img]");
SelectPaginas --> //*[@id='blog-pager-older-link']


--- https://alturgell-xgrane.blogspot.com/
post -> afinar (recull tots els comentaris del post)
--> //*[@id="Blog1"]/div[1]/div/div/div/div[1]//*[not(@id='comments')]
--> //*[@id="Blog1"]/div[1]/div/div/div/div[1]/::*[not(ancestor-or-self::id:comments)]

Importar el <mT:translation> elemento y sus descendientes, excepto el elemento <mT:ignore>
//mT:translation/descendant-or-self::*[not(ancestor-or-self::mT:ignore)]


---https://roadtoalps.wordpress.com/
private function extract_post_data($url) 


# ---- canvis per producció: ----
Aparença -> Personaliza -> Capçalera --> elimina [tags-orientacio]
Blocksy Child (actiu) --> Fitxer: template-parts/archive.php --> 62 --> //echo do_shortcode( '[flexy_breadcrumb]');
css:
.meta-author{
	display:none!important;
}
.meta-date{
	display:none!important;
}
.page-description {
	min-height:565px!important;
	min-width:290px!important;
}

.entry-content.is-layout-constrained{
	display:none!important;
}

functions.php:
-> add_action( 'pre_get_posts', 'exclude_category' ); /* general */