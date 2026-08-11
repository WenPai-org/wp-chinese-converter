#!/usr/bin/env python3
import json
import os
import xmlrpc.client

server = xmlrpc.client.ServerProxy(
    os.environ["WPCC_XMLRPC_URL"].rstrip("/") + "/xmlrpc.php", allow_none=True
)
blog_id = int(os.environ["WPCC_BLOG_ID"])
action = os.environ.get("WPCC_XMLRPC_ACTION", "create")
payload = {
    "post_type": "post",
    "post_status": "publish",
    "post_title": os.environ.get(
        "WPCC_POST_TITLE", os.environ.get("WPCC_MARKER", "wpcc-runtime") + " 网络软件"
    ),
    "post_content": os.environ.get("WPCC_POST_CONTENT", "网络软件和后台打印机"),
}

if action == "create":
    post_id = server.wp.newPost(
        blog_id,
        os.environ["WPCC_ADMIN_USER"],
        os.environ["WPCC_APP_PASSWORD"],
        payload,
    )
    result = {"post_id": int(post_id), "blog_id": blog_id, "transport": "xmlrpc", "action": "create"}
elif action == "update":
    post_id = int(os.environ["WPCC_XMLRPC_POST_ID"])
    updated = server.wp.editPost(
        blog_id,
        os.environ["WPCC_ADMIN_USER"],
        os.environ["WPCC_APP_PASSWORD"],
        post_id,
        payload,
    )
    if not updated:
        raise RuntimeError("wp.editPost returned false")
    result = {"post_id": post_id, "blog_id": blog_id, "transport": "xmlrpc", "action": "update"}
else:
    raise ValueError(f"unsupported WPCC_XMLRPC_ACTION: {action}")

print(json.dumps(result))
