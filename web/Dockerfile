FROM httpd:2.4-alpine

COPY web/public/ /usr/local/apache2/htdocs/

RUN cat > /usr/local/apache2/conf/extra/studyboard.conf <<'EOF'
ServerName localhost

<Directory "/usr/local/apache2/htdocs">
    Options -Indexes
    AllowOverride None
    Require all granted
</Directory>

RewriteEngine On

# Preserva arquivos e diretórios reais, como CSS, JS, imagens e vendor.
RewriteCond /usr/local/apache2/htdocs%{REQUEST_URI} -f [OR]
RewriteCond /usr/local/apache2/htdocs%{REQUEST_URI} -d
RewriteRule ^ - [END]

# Permite URLs sem extensão, como /login, /tarefas e /perfil.
RewriteCond /usr/local/apache2/htdocs%{REQUEST_URI}.html -f
RewriteRule ^(.+)$ $1.html [END]

# Fallback para a página inicial apenas quando não for arquivo real.
RewriteRule ^ /index.html [END]
EOF

RUN sed -i 's/Listen 80/Listen 8080/' /usr/local/apache2/conf/httpd.conf \
    && sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /usr/local/apache2/conf/httpd.conf \
    && echo 'Include conf/extra/studyboard.conf' >> /usr/local/apache2/conf/httpd.conf \
    && chown -R 10001:10001 /usr/local/apache2

USER 10001
EXPOSE 8080

CMD ["httpd-foreground"]
