# Apache 2.4 with the modules CommonSight needs (.htaccess, PHP via FastCGI).
FROM httpd:2.4

RUN sed -i -E \
      's/^#(LoadModule (proxy_module|proxy_fcgi_module|rewrite_module|headers_module|expires_module|deflate_module|filter_module|setenvif_module|env_module|mime_module) )/\1/' \
      conf/httpd.conf \
 && echo 'Include conf/extra/commonsight.conf' >> conf/httpd.conf

COPY commonsight.conf conf/extra/commonsight.conf
