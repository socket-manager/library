#ifdef HAVE_CONFIG_H
# include "config.h"
#endif

#include "php.h"

/* ========= 共通定義 ========= */

#ifdef PHP_WIN32
# include <winsock2.h>
# include <ws2tcpip.h>
typedef SOCKET PHP_SOCKET;
# define PHP_SOCKETS_INVALID_SOCKET INVALID_SOCKET
#else
# include "ext/sockets/php_sockets.h"  /* 本物の sockets 拡張に依存 */
# define PHP_SOCKETS_INVALID_SOCKET -1
#endif

#ifdef PHP_WIN32

typedef struct {
    PHP_SOCKET  bsd_socket;
    int         type;
    int         error;
    int         blocking;
    zval        zstream;
    zend_object std;
} php_socket;

static zend_class_entry *socket_ce;
static zend_object_handlers socket_object_handlers;

static inline php_socket *php_socket_from_obj(zend_object *obj)
{
    return (php_socket *)((char *)obj - XtOffsetOf(php_socket, std));
}

# define Z_SOCKET_P(zv) php_socket_from_obj(Z_OBJ_P((zv)))

# define ENSURE_SOCKET_VALID(php_sock) \
    do { \
        if ((php_sock)->bsd_socket == PHP_SOCKETS_INVALID_SOCKET) { \
            php_error_docref(NULL, E_WARNING, "Invalid or closed socket"); \
            RETURN_FALSE; \
        } \
    } while (0)

/* ========= Socket オブジェクト生成（Windows 専用） ========= */

static zend_object *socket_object_create(zend_class_entry *ce)
{
    php_socket *sock = zend_object_alloc(sizeof(php_socket), ce);

    sock->bsd_socket = PHP_SOCKETS_INVALID_SOCKET;
    sock->type       = 0;
    sock->error      = 0;
    sock->blocking   = 1;
    ZVAL_UNDEF(&sock->zstream);

    zend_object_std_init(&sock->std, ce);
    object_properties_init(&sock->std, ce);
    sock->std.handlers = &socket_object_handlers;

    return &sock->std;
}

static void socket_object_free(zend_object *object)
{
    php_socket *sock = php_socket_from_obj(object);

    if (sock->bsd_socket != PHP_SOCKETS_INVALID_SOCKET) {
        closesocket(sock->bsd_socket);
        sock->bsd_socket = PHP_SOCKETS_INVALID_SOCKET;
    }

    zend_object_std_dtor(&sock->std);
}

#else /* 非 Windows 側 */

extern zend_class_entry *socket_ce; /* ext/sockets が定義しているものを使う */

#endif /* PHP_WIN32 */

/* ========= arginfo ========= */

ZEND_BEGIN_ARG_INFO_EX(arginfo_socketsfd, 0, 0, 1)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
ZEND_END_ARG_INFO()

#ifdef PHP_WIN32
ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_import_fd, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, fd, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_create, 0, 0, 0)
    ZEND_ARG_TYPE_INFO(0, domain, IS_LONG, 1)
    ZEND_ARG_TYPE_INFO(0, type, IS_LONG, 1)
    ZEND_ARG_TYPE_INFO(0, protocol, IS_LONG, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_read, 0, 0, 2)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
    ZEND_ARG_TYPE_INFO(0, length, IS_LONG, 0)
    ZEND_ARG_TYPE_INFO(0, flags, IS_LONG, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_write, 0, 0, 2)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
    ZEND_ARG_TYPE_INFO(0, data, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, length, IS_LONG, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_recvfrom, 0, 0, 4)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
    ZEND_ARG_TYPE_INFO(1, buf, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, length, IS_LONG, 0)
    ZEND_ARG_TYPE_INFO(0, flags, IS_LONG, 1)
    ZEND_ARG_INFO(1, address)
    ZEND_ARG_INFO(1, port)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_sendto, 0, 0, 4)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
    ZEND_ARG_TYPE_INFO(0, data, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, flags, IS_LONG, 1)
    ZEND_ARG_TYPE_INFO(0, address, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, port, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_connect, 0, 0, 2)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
    ZEND_ARG_TYPE_INFO(0, address, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, port, IS_LONG, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_bind, 0, 0, 2)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
    ZEND_ARG_TYPE_INFO(0, address, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, port, IS_LONG, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_listen, 0, 0, 1)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
    ZEND_ARG_TYPE_INFO(0, backlog, IS_LONG, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_accept, 0, 0, 1)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_getsockname, 0, 0, 2)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
    ZEND_ARG_INFO(1, address)
    ZEND_ARG_INFO(1, port)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_getpeername, 0, 0, 2)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
    ZEND_ARG_INFO(1, address)
    ZEND_ARG_INFO(1, port)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_set_option, 0, 0, 4)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
    ZEND_ARG_TYPE_INFO(0, level, IS_LONG, 0)
    ZEND_ARG_TYPE_INFO(0, optname, IS_LONG, 0)
    ZEND_ARG_INFO(0, optval)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_get_option, 0, 0, 3)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
    ZEND_ARG_TYPE_INFO(0, level, IS_LONG, 0)
    ZEND_ARG_TYPE_INFO(0, optname, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_set_nonblock, 0, 0, 1)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_shutdown, 0, 0, 1)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
    ZEND_ARG_TYPE_INFO(0, how, IS_LONG, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_close, 0, 0, 1)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_last_error, 0, 0, 0)
    ZEND_ARG_OBJ_INFO(0, socket, Socket, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_socket_strerror, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, errno, IS_LONG, 0)
ZEND_END_ARG_INFO()
#endif

/* ========= 関数実装 ========= */

/* proto int socketsfd(Socket $socket) */
PHP_FUNCTION(socketsfd)
{
    zval *zsock;
    php_socket *php_sock;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
    ZEND_PARSE_PARAMETERS_END();

    php_sock = Z_SOCKET_P(zsock);
    if (!php_sock) {
        RETURN_FALSE;
    }

    ENSURE_SOCKET_VALID(php_sock);

#ifdef PHP_WIN32
    RETURN_LONG((zend_long) php_sock->bsd_socket);
#else
    RETURN_LONG(php_sock->bsd_socket);
#endif
}

#ifdef PHP_WIN32
/* proto Socket socket_import_fd(int $fd) */
PHP_FUNCTION(socket_import_fd)
{
    zend_long fd;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_LONG(fd)
    ZEND_PARSE_PARAMETERS_END();

    if (fd <= 0) {
        php_error_docref(NULL, E_WARNING, "Invalid fd");
        RETURN_FALSE;
    }

    SOCKET s = (SOCKET)fd;

    /* 非ブロッキングにしておく（IOCP 前提） */
    {
        u_long mode = 1;
        if (ioctlsocket(s, FIONBIO, &mode) == SOCKET_ERROR) {
            php_error_docref(NULL, E_WARNING,
                "ioctlsocket(FIONBIO) failed: %d", WSAGetLastError());
            RETURN_FALSE;
        }
    }

    /* Socket オブジェクトを生成 */
    zval zsock_obj;
    object_init_ex(&zsock_obj, socket_ce);

    php_socket *php_sock = Z_SOCKET_P(&zsock_obj);
    php_sock->bsd_socket = s;
    php_sock->type       = SOCK_STREAM;
    php_sock->error      = 0;
    php_sock->blocking   = 0;

    RETURN_ZVAL(&zsock_obj, 1, 0);
}

/* proto Socket socket_create(int $domain = AF_INET, int $type = SOCK_STREAM, int $protocol = SOL_TCP) */
PHP_FUNCTION(socket_create)
{
    zend_long domain = AF_INET;
    zend_long type = SOCK_STREAM;
    zend_long protocol = IPPROTO_TCP;

    ZEND_PARSE_PARAMETERS_START(0, 3)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(domain)
        Z_PARAM_LONG(type)
        Z_PARAM_LONG(protocol)
    ZEND_PARSE_PARAMETERS_END();

    PHP_SOCKET s = WSASocket(
        (int)domain,
        (int)type,
        (int)protocol,
        NULL,
        0,
        WSA_FLAG_OVERLAPPED
    );

    if (s == PHP_SOCKETS_INVALID_SOCKET) {
        php_error_docref(NULL, E_WARNING,
            "socket_create failed: %d", WSAGetLastError());
        RETURN_FALSE;
    }

    /* IOCP 前提なら非ブロッキングにしておく */
    {
        u_long mode = 1;
        if (ioctlsocket(s, FIONBIO, &mode) == SOCKET_ERROR) {
            php_error_docref(NULL, E_WARNING,
                "ioctlsocket(FIONBIO) failed: %d", WSAGetLastError());
            closesocket(s);
            RETURN_FALSE;
        }
    }

    zval zsock_obj;
    object_init_ex(&zsock_obj, socket_ce);

    php_socket *php_sock = Z_SOCKET_P(&zsock_obj);
    php_sock->bsd_socket = s;
    php_sock->type       = (int)type;
    php_sock->error      = 0;
    php_sock->blocking   = 0;

    RETURN_ZVAL(&zsock_obj, 1, 0);
}

/* proto string|false socket_read(Socket $socket, int $length, int $flags = 0) */
PHP_FUNCTION(socket_read)
{
    zval *zsock;
    zend_long length;
    zend_long flags = 0;

    ZEND_PARSE_PARAMETERS_START(2, 3)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
        Z_PARAM_LONG(length)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(flags)
    ZEND_PARSE_PARAMETERS_END();

    if (length <= 0) {
        php_error_docref(NULL, E_WARNING, "Length must be greater than 0");
        RETURN_FALSE;
    }

    php_socket *php_sock = Z_SOCKET_P(zsock);
    ENSURE_SOCKET_VALID(php_sock);

    if (length > INT_MAX) {
        length = INT_MAX;
    }

    zend_string *buf = zend_string_alloc((size_t)length, 0);

    int n = recv(php_sock->bsd_socket, ZSTR_VAL(buf), (int)length, (int)flags);

    if (n <= 0) {
        int err = WSAGetLastError();
        zend_string_free(buf);

        if (n == 0 || err == WSAEWOULDBLOCK) {
            /* データなし → 空文字列を返す */
            RETURN_EMPTY_STRING();
        }

        php_sock->error = err;
        RETURN_FALSE;
    }

    ZSTR_VAL(buf)[n] = '\0';
    ZSTR_LEN(buf) = n;

    RETURN_STR(buf);
}

/* proto int|false socket_write(Socket $socket, string $data, int $length = 0) */
PHP_FUNCTION(socket_write)
{
    zval *zsock;
    zend_string *data;
    zend_long length = 0;

    ZEND_PARSE_PARAMETERS_START(2, 3)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
        Z_PARAM_STR(data)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(length)
    ZEND_PARSE_PARAMETERS_END();

    php_socket *php_sock = Z_SOCKET_P(zsock);
    ENSURE_SOCKET_VALID(php_sock);

    /* length が 0 または未指定なら全長 */
    size_t to_send = ZSTR_LEN(data);
    if (length > 0 && (size_t)length < to_send) {
        to_send = (size_t)length;
    }

    int n = send(
        php_sock->bsd_socket,
        ZSTR_VAL(data),
        (int)to_send,
        0               /* ← flags は常に 0 */
    );

    if (n == SOCKET_ERROR) {
        int err = WSAGetLastError();

        if (err == WSAEWOULDBLOCK) {
            /* 今は送れない → 進捗なし */
            RETURN_LONG(0);
        }

        php_sock->error = err;
        RETURN_FALSE;
    }

    RETURN_LONG(n);
}

PHP_FUNCTION(socket_recvfrom)
{
    zval *zsock;
    zval *zbuf, *zaddr, *zport;
    zend_long length, flags = 0;

    ZEND_PARSE_PARAMETERS_START(4, 6)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
        Z_PARAM_ZVAL(zbuf)
        Z_PARAM_LONG(length)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(flags)
        Z_PARAM_ZVAL(zaddr)
        Z_PARAM_ZVAL(zport)
    ZEND_PARSE_PARAMETERS_END();

    php_socket *php_sock = Z_SOCKET_P(zsock);
    ENSURE_SOCKET_VALID(php_sock);

    if (length <= 0) {
        php_error_docref(NULL, E_WARNING, "Length must be greater than 0");
        RETURN_FALSE;
    }

    struct sockaddr_in sa;
    int sa_len = sizeof(sa);

    zend_string *buf = zend_string_alloc((size_t)length, 0);

    int n = recvfrom(
        php_sock->bsd_socket,
        ZSTR_VAL(buf),
        (int)length,
        (int)flags,
        (struct sockaddr *)&sa,
        &sa_len
    );

    if (n == SOCKET_ERROR) {
        zend_string_free(buf);
        php_sock->error = WSAGetLastError();
        RETURN_FALSE;
    }

    ZSTR_VAL(buf)[n] = '\0';
    ZSTR_LEN(buf) = n;

    /* 参照返し */
    ZVAL_STR(zbuf, buf);

    if (zaddr) {
        ZVAL_STRING(zaddr, inet_ntoa(sa.sin_addr));
    }
    if (zport) {
        ZVAL_LONG(zport, ntohs(sa.sin_port));
    }

    RETURN_LONG(n);
}

PHP_FUNCTION(socket_sendto)
{
    zval *zsock;
    zend_string *data;
    zend_long flags = 0;
    zend_string *address;
    zend_long port;

    ZEND_PARSE_PARAMETERS_START(4, 5)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
        Z_PARAM_STR(data)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(flags)
        Z_PARAM_STR(address)
        Z_PARAM_LONG(port)
    ZEND_PARSE_PARAMETERS_END();

    php_socket *php_sock = Z_SOCKET_P(zsock);
    ENSURE_SOCKET_VALID(php_sock);

    struct sockaddr_in sa;
    memset(&sa, 0, sizeof(sa));
    sa.sin_family = AF_INET;
    sa.sin_port = htons((u_short)port);
    sa.sin_addr.s_addr = inet_addr(ZSTR_VAL(address));

    int n = sendto(
        php_sock->bsd_socket,
        ZSTR_VAL(data),
        (int)ZSTR_LEN(data),
        (int)flags,
        (struct sockaddr *)&sa,
        sizeof(sa)
    );

    if (n == SOCKET_ERROR) {
        php_sock->error = WSAGetLastError();
        RETURN_FALSE;
    }

    RETURN_LONG(n);
}

PHP_FUNCTION(socket_connect)
{
    zval *zsock;
    zend_string *address;
    zend_long port = 0;

    ZEND_PARSE_PARAMETERS_START(2, 3)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
        Z_PARAM_STR(address)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(port)
    ZEND_PARSE_PARAMETERS_END();

    php_socket *php_sock = Z_SOCKET_P(zsock);
    ENSURE_SOCKET_VALID(php_sock);

    struct sockaddr_in sa;
    memset(&sa, 0, sizeof(sa));
    sa.sin_family = AF_INET;
    sa.sin_port = htons((u_short)port);
    sa.sin_addr.s_addr = inet_addr(ZSTR_VAL(address));

    int ret = connect(php_sock->bsd_socket, (struct sockaddr *)&sa, sizeof(sa));

    if (ret == 0) {
        RETURN_TRUE;
    }

    int err = WSAGetLastError();

    if (err == WSAEWOULDBLOCK || err == WSAEINPROGRESS) {
        /* 非同期 connect の正常パス */
        RETURN_TRUE;
    }

    php_sock->error = err;
    RETURN_FALSE;
}

PHP_FUNCTION(socket_bind)
{
    zval *zsock;
    zend_string *address;
    zend_long port = 0;

    ZEND_PARSE_PARAMETERS_START(2, 3)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
        Z_PARAM_STR(address)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(port)
    ZEND_PARSE_PARAMETERS_END();

    php_socket *php_sock = Z_SOCKET_P(zsock);
    ENSURE_SOCKET_VALID(php_sock);

    struct sockaddr_in sa;
    memset(&sa, 0, sizeof(sa));
    sa.sin_family = AF_INET;
    sa.sin_port = htons((u_short)port);
    sa.sin_addr.s_addr = inet_addr(ZSTR_VAL(address));

    int ret = bind(php_sock->bsd_socket, (struct sockaddr *)&sa, sizeof(sa));

    if (ret == SOCKET_ERROR) {
        php_sock->error = WSAGetLastError();
        RETURN_FALSE;
    }

    RETURN_TRUE;
}

PHP_FUNCTION(socket_listen)
{
    zval *zsock;
    zend_long backlog = SOMAXCONN;

    ZEND_PARSE_PARAMETERS_START(1, 2)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(backlog)
    ZEND_PARSE_PARAMETERS_END();

    php_socket *php_sock = Z_SOCKET_P(zsock);
    ENSURE_SOCKET_VALID(php_sock);

    int ret = listen(php_sock->bsd_socket, (int)backlog);

    if (ret == SOCKET_ERROR) {
        php_sock->error = WSAGetLastError();
        RETURN_FALSE;
    }

    RETURN_TRUE;
}

PHP_FUNCTION(socket_accept)
{
    zval *zsock;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
    ZEND_PARSE_PARAMETERS_END();

    php_socket *php_sock = Z_SOCKET_P(zsock);
    ENSURE_SOCKET_VALID(php_sock);

    struct sockaddr_in sa;
    int sa_len = sizeof(sa);

    SOCKET client = accept(php_sock->bsd_socket, (struct sockaddr *)&sa, &sa_len);

    if (client == INVALID_SOCKET) {
        php_sock->error = WSAGetLastError();
        RETURN_FALSE;
    }

    /* 非ブロッキングにする（IOCP 前提） */
    {
        u_long mode = 1;
        ioctlsocket(client, FIONBIO, &mode);
    }

    /* 新しい Socket オブジェクトを返す */
    zval znew;
    object_init_ex(&znew, socket_ce);

    php_socket *new_sock = Z_SOCKET_P(&znew);
    new_sock->bsd_socket = client;
    new_sock->type       = SOCK_STREAM;
    new_sock->error      = 0;
    new_sock->blocking   = 0;

    RETURN_ZVAL(&znew, 1, 0);
}

PHP_FUNCTION(socket_getsockname)
{
    zval *zsock;
    zval *zaddr, *zport;

    ZEND_PARSE_PARAMETERS_START(3, 3)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
        Z_PARAM_ZVAL(zaddr)
        Z_PARAM_ZVAL(zport)
    ZEND_PARSE_PARAMETERS_END();

    php_socket *php_sock = Z_SOCKET_P(zsock);
    ENSURE_SOCKET_VALID(php_sock);

    struct sockaddr_in sa;
    int sa_len = sizeof(sa);

    if (getsockname(php_sock->bsd_socket, (struct sockaddr *)&sa, &sa_len) == SOCKET_ERROR) {
        php_sock->error = WSAGetLastError();
        RETURN_FALSE;
    }

    ZVAL_STRING(zaddr, inet_ntoa(sa.sin_addr));
    ZVAL_LONG(zport, ntohs(sa.sin_port));

    RETURN_TRUE;
}

PHP_FUNCTION(socket_getpeername)
{
    zval *zsock;
    zval *zaddr, *zport;

    ZEND_PARSE_PARAMETERS_START(3, 3)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
        Z_PARAM_ZVAL(zaddr)
        Z_PARAM_ZVAL(zport)
    ZEND_PARSE_PARAMETERS_END();

    php_socket *php_sock = Z_SOCKET_P(zsock);
    ENSURE_SOCKET_VALID(php_sock);

    struct sockaddr_in sa;
    int sa_len = sizeof(sa);

    if (getpeername(php_sock->bsd_socket, (struct sockaddr *)&sa, &sa_len) == SOCKET_ERROR) {
        php_sock->error = WSAGetLastError();
        RETURN_FALSE;
    }

    ZVAL_STRING(zaddr, inet_ntoa(sa.sin_addr));
    ZVAL_LONG(zport, ntohs(sa.sin_port));

    RETURN_TRUE;
}

PHP_FUNCTION(socket_set_option)
{
    zval *zsock;
    zend_long level, optname;
    zval *optval;

    ZEND_PARSE_PARAMETERS_START(4, 4)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
        Z_PARAM_LONG(level)
        Z_PARAM_LONG(optname)
        Z_PARAM_ZVAL(optval)
    ZEND_PARSE_PARAMETERS_END();

    php_socket *php_sock = Z_SOCKET_P(zsock);
    ENSURE_SOCKET_VALID(php_sock);

    int ret;

    if (Z_TYPE_P(optval) == IS_LONG) {
        int val = Z_LVAL_P(optval);
        ret = setsockopt(
            php_sock->bsd_socket,
            (int)level,
            (int)optname,
            (char *)&val,
            sizeof(val)
        );
    } else if (Z_TYPE_P(optval) == IS_STRING) {
        ret = setsockopt(
            php_sock->bsd_socket,
            (int)level,
            (int)optname,
            Z_STRVAL_P(optval),
            (int)Z_STRLEN_P(optval)
        );
    } else {
        php_error_docref(NULL, E_WARNING, "optval must be int or string");
        RETURN_FALSE;
    }

    if (ret == SOCKET_ERROR) {
        php_sock->error = WSAGetLastError();
        RETURN_FALSE;
    }

    RETURN_TRUE;
}

PHP_FUNCTION(socket_get_option)
{
    zval *zsock;
    zend_long level, optname;

    ZEND_PARSE_PARAMETERS_START(3, 3)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
        Z_PARAM_LONG(level)
        Z_PARAM_LONG(optname)
    ZEND_PARSE_PARAMETERS_END();

    php_socket *php_sock = Z_SOCKET_P(zsock);
    ENSURE_SOCKET_VALID(php_sock);

    char buf[256];
    int len = sizeof(buf);

    int ret = getsockopt(
        php_sock->bsd_socket,
        (int)level,
        (int)optname,
        buf,
        &len
    );

    if (ret == SOCKET_ERROR) {
        php_sock->error = WSAGetLastError();
        RETURN_FALSE;
    }

    /* int の場合 */
    if (len == sizeof(int)) {
        int val;
        memcpy(&val, buf, sizeof(int));
        RETURN_LONG(val);
    }

    /* その他は string として返す */
    RETURN_STRINGL(buf, len);
}

PHP_FUNCTION(socket_set_nonblock)
{
    zval *zsock;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
    ZEND_PARSE_PARAMETERS_END();

    /* すでに非ブロッキングなので何もしない */
    RETURN_TRUE;
}

PHP_FUNCTION(socket_shutdown)
{
    zval *zsock;
    zend_long how = 2; /* ext/sockets のデフォルト */

    ZEND_PARSE_PARAMETERS_START(1, 2)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(how)
    ZEND_PARSE_PARAMETERS_END();

    /* IOCP 側で管理するため何もしない */
    RETURN_TRUE;
}

PHP_FUNCTION(socket_close)
{
    zval *zsock;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
    ZEND_PARSE_PARAMETERS_END();

    /* 実際のクローズは IOCP / オブジェクト free_obj に任せる */
    RETURN_TRUE;
}

PHP_FUNCTION(socket_last_error)
{
    zval *zsock = NULL;

    ZEND_PARSE_PARAMETERS_START(0, 1)
        Z_PARAM_OPTIONAL
        Z_PARAM_OBJECT_OF_CLASS(zsock, socket_ce)
    ZEND_PARSE_PARAMETERS_END();

    if (zsock) {
        php_socket *php_sock = Z_SOCKET_P(zsock);
        ENSURE_SOCKET_VALID(php_sock);
        RETURN_LONG(php_sock->error);
    }

    RETURN_LONG(WSAGetLastError());
}

PHP_FUNCTION(socket_strerror)
{
    zend_long err;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_LONG(err)
    ZEND_PARSE_PARAMETERS_END();

    LPWSTR wbuf = NULL;

    DWORD len = FormatMessageW(
        FORMAT_MESSAGE_ALLOCATE_BUFFER |
        FORMAT_MESSAGE_FROM_SYSTEM |
        FORMAT_MESSAGE_IGNORE_INSERTS,
        NULL,
        (DWORD)err,
        MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT),
        (LPWSTR)&wbuf,
        0,
        NULL
    );

    if (len == 0 || wbuf == NULL) {
        RETURN_STRING("Unknown error");
    }

    /* UTF-16 → UTF-8 変換 */
    int utf8_len = WideCharToMultiByte(
        CP_UTF8, 0, wbuf, len, NULL, 0, NULL, NULL
    );

    zend_string *ret = zend_string_alloc(utf8_len, 0);

    WideCharToMultiByte(
        CP_UTF8, 0, wbuf, len, ZSTR_VAL(ret), utf8_len, NULL, NULL
    );

    ZSTR_VAL(ret)[utf8_len] = '\0';

    LocalFree(wbuf);

    /* 末尾の改行削除 */
    while (utf8_len > 0 &&
           (ZSTR_VAL(ret)[utf8_len - 1] == '\n' ||
            ZSTR_VAL(ret)[utf8_len - 1] == '\r')) {
        ZSTR_VAL(ret)[--utf8_len] = '\0';
        ZSTR_LEN(ret) = utf8_len;
    }

    RETURN_STR(ret);
}
#endif /* PHP_WIN32 */

/* ========= 関数テーブル ========= */

static const zend_function_entry socketsfd_functions[] = {
    PHP_FE(socketsfd,        arginfo_socketsfd)
#ifdef PHP_WIN32
    PHP_FE(socket_import_fd,    arginfo_socket_import_fd)
    PHP_FE(socket_create,       arginfo_socket_create)
    PHP_FE(socket_read,         arginfo_socket_read)
    PHP_FE(socket_write,        arginfo_socket_write)
    PHP_FE(socket_recvfrom,     arginfo_socket_recvfrom)
    PHP_FE(socket_sendto,       arginfo_socket_sendto)
    PHP_FE(socket_connect,      arginfo_socket_connect)
    PHP_FE(socket_bind,         arginfo_socket_bind)
    PHP_FE(socket_listen,       arginfo_socket_listen)
    PHP_FE(socket_accept,       arginfo_socket_accept)
    PHP_FE(socket_getsockname,  arginfo_socket_getsockname)
    PHP_FE(socket_getpeername,  arginfo_socket_getpeername)
    PHP_FE(socket_set_option,   arginfo_socket_set_option)
    PHP_FE(socket_get_option,   arginfo_socket_get_option)
    PHP_FE(socket_set_nonblock, arginfo_socket_set_nonblock)
    PHP_FE(socket_shutdown,     arginfo_socket_shutdown)
    PHP_FE(socket_close,        arginfo_socket_close)
    PHP_FE(socket_last_error,   arginfo_socket_last_error)
    PHP_FE(socket_strerror,     arginfo_socket_strerror)
#endif
    PHP_FE_END
};

/* ========= MINIT ========= */

PHP_MINIT_FUNCTION(socketsfd)
{
#ifdef PHP_WIN32
    REGISTER_LONG_CONSTANT("AF_INET", AF_INET, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("SOCK_STREAM", SOCK_STREAM, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("SOCK_DGRAM", SOCK_DGRAM, CONST_CS | CONST_PERSISTENT);

    REGISTER_LONG_CONSTANT("SOL_TCP", IPPROTO_TCP, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("SOL_UDP", IPPROTO_UDP, CONST_CS | CONST_PERSISTENT);

    REGISTER_LONG_CONSTANT("SOL_SOCKET", SOL_SOCKET, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("SO_REUSEADDR", SO_REUSEADDR, CONST_CS | CONST_PERSISTENT);

    zend_class_entry ce;

    INIT_CLASS_ENTRY(ce, "Socket", NULL);
    socket_ce = zend_register_internal_class(&ce);
    socket_ce->create_object = socket_object_create;

    memcpy(&socket_object_handlers, zend_get_std_object_handlers(), sizeof(zend_object_handlers));
    socket_object_handlers.offset   = XtOffsetOf(php_socket, std);
    socket_object_handlers.free_obj = socket_object_free;
#endif
    return SUCCESS;
}

/* ========= モジュールエントリ ========= */

zend_module_entry socketsfd_module_entry = {
    STANDARD_MODULE_HEADER,
    "socketsfd",
    socketsfd_functions,
    PHP_MINIT(socketsfd),
    NULL,
    NULL,
    NULL,
    NULL,
    NO_VERSION_YET,
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_SOCKETSFD
ZEND_GET_MODULE(socketsfd)
#endif
