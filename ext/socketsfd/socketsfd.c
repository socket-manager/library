#ifdef HAVE_CONFIG_H
# include "config.h"
#endif

#include "php.h"
#include "ext/sockets/php_sockets.h"

/* {{{ arginfo */
ZEND_BEGIN_ARG_INFO_EX(arginfo_socketsfd, 0, 0, 1)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
ZEND_END_ARG_INFO()
/* }}} */

/* {{{ proto int socketsfd(Socket $socket)
   Return underlying SOCKET/fd from ext/sockets Socket object */
PHP_FUNCTION(socketsfd)
{
    zval *zsock;
    php_socket *php_sock;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
    ZEND_PARSE_PARAMETERS_END();

    php_sock = Z_SOCKET_P(zsock);
    if(!php_sock)
    {
        RETURN_FALSE;
    }

    ENSURE_SOCKET_VALID(php_sock);

#ifdef PHP_WIN32
    RETURN_LONG((zend_long) php_sock->bsd_socket);
#else
    RETURN_LONG(php_sock->bsd_socket);
#endif
}
/* }}} */

static const zend_function_entry socketsfd_functions[] = {
    PHP_FE(socketsfd, arginfo_socketsfd)
    PHP_FE_END
};

zend_module_entry socketsfd_module_entry = {
    STANDARD_MODULE_HEADER,
    "socketsfd",                 /* extension name */
    socketsfd_functions,         /* functions */
    NULL,                       /* MINIT */
    NULL,                       /* MSHUTDOWN */
    NULL,                       /* RINIT */
    NULL,                       /* RSHUTDOWN */
    NULL,                       /* MINFO */
    NO_VERSION_YET,
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_SOCKETSFD
ZEND_GET_MODULE(socketsfd)
#endif
