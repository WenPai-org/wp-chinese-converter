#!/usr/bin/env python3
import json, os, xmlrpc.client
server = xmlrpc.client.ServerProxy(os.environ["WPCC_XMLRPC_URL"].rstrip("/") + "/xmlrpc.php", allow_none=True)
blog_id = int(os.environ["WPCC_BLOG_ID"])
post_id = server.wp.newPost(blog_id, os.environ["WPCC_ADMIN_USER"], os.environ["WPCC_APP_PASSWORD"], {
    "post_type": "post", "post_status": "publish",
    "post_title": os.environ["WPCC_MARKER"] + " 网络软件",
    "post_content": "网络软件和后台打印机",
})
print(json.dumps({"post_id": int(post_id), "blog_id": blog_id, "transport": "xmlrpc"}))
