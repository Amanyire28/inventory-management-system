## GitHub Copilot Chat

- Extension: 0.37.1 (prod)
- VS Code: 1.109.0 (bdd88df003631aaa0bcbe057cb0a940b80a476fa)
- OS: win32 10.0.22000 x64
- GitHub Account: Amanyire28

## Network

User Settings:
```json
  "http.systemCertificatesNode": false,
  "github.copilot.advanced.debug.useElectronFetcher": true,
  "github.copilot.advanced.debug.useNodeFetcher": false,
  "github.copilot.advanced.debug.useNodeFetchFetcher": true
```

Connecting to https://api.github.com:
- DNS ipv4 Lookup: 140.82.121.5 (875 ms)
- DNS ipv6 Lookup: Error (43 ms): getaddrinfo ENOTFOUND api.github.com
- Proxy URL: None (4 ms)
- Electron fetch (configured): Error (37 ms): Error: net::ERR_CONNECTION_CLOSED
	at SimpleURLLoaderWrapper.<anonymous> (node:electron/js2c/utility_init:2:10684)
	at SimpleURLLoaderWrapper.emit (node:events:519:28)
  [object Object]
  {"is_request_error":true,"network_process_crashed":false}
- Node.js https: Error (125 ms): Error: Client network socket disconnected before secure TLS connection was established
	at TLSSocket.onConnectEnd (node:_tls_wrap:1732:19)
	at TLSSocket.emit (node:events:531:35)
	at endReadableNT (node:internal/streams/readable:1698:12)
	at process.processTicksAndRejections (node:internal/process/task_queues:90:21)
- Node.js fetch: Error (165 ms): TypeError: fetch failed
	at node:internal/deps/undici/undici:14900:13
	at process.processTicksAndRejections (node:internal/process/task_queues:105:5)
	at async n._fetch (c:\Users\Admin\.vscode\extensions\github.copilot-chat-0.37.1\dist\extension.js:4753:26157)
	at async n.fetch (c:\Users\Admin\.vscode\extensions\github.copilot-chat-0.37.1\dist\extension.js:4753:25805)
	at async d (c:\Users\Admin\.vscode\extensions\github.copilot-chat-0.37.1\dist\extension.js:4785:190)
	at async vA.h (file:///c:/Users/Admin/AppData/Local/Programs/Microsoft%20VS%20Code/bdd88df003/resources/app/out/vs/workbench/api/node/extensionHostProcess.js:116:41743)
  Error: Client network socket disconnected before secure TLS connection was established
  	at TLSSocket.onConnectEnd (node:_tls_wrap:1732:19)
  	at TLSSocket.emit (node:events:531:35)
  	at endReadableNT (node:internal/streams/readable:1698:12)
  	at process.processTicksAndRejections (node:internal/process/task_queues:90:21)

Connecting to https://api.githubcopilot.com/_ping:
- DNS ipv4 Lookup: 140.82.113.21 (70 ms)
- DNS ipv6 Lookup: Error (37 ms): getaddrinfo ENOTFOUND api.githubcopilot.com
- Proxy URL: None (10 ms)
- Electron fetch (configured): Error (38 ms): Error: net::ERR_CONNECTION_CLOSED
	at SimpleURLLoaderWrapper.<anonymous> (node:electron/js2c/utility_init:2:10684)
	at SimpleURLLoaderWrapper.emit (node:events:519:28)
  [object Object]
  {"is_request_error":true,"network_process_crashed":false}
- Node.js https: Error (73 ms): Error: Client network socket disconnected before secure TLS connection was established
	at TLSSocket.onConnectEnd (node:_tls_wrap:1732:19)
	at TLSSocket.emit (node:events:531:35)
	at endReadableNT (node:internal/streams/readable:1698:12)
	at process.processTicksAndRejections (node:internal/process/task_queues:90:21)z
- Node.js fetch: Error (164 ms): TypeError: fetch failed
	at node:internal/deps/undici/undici:14900:13
	at process.processTicksAndRejections (node:internal/process/task_queues:105:5)
	at async n._fetch (c:\Users\Admin\.vscode\extensions\github.copilot-chat-0.37.1\dist\extension.js:4753:26157)
	at async n.fetch (c:\Users\Admin\.vscode\extensions\github.copilot-chat-0.37.1\dist\extension.js:4753:25805)
	at async d (c:\Users\Admin\.vscode\extensions\github.copilot-chat-0.37.1\dist\extension.js:4785:190)
	at async vA.h (file:///c:/Users/Admin/AppData/Local/Programs/Microsoft%20VS%20Code/bdd88df003/resources/app/out/vs/workbench/api/node/extensionHostProcess.js:116:41743)
  Error: Client network socket disconnected before secure TLS connection was established
  	at TLSSocket.onConnectEnd (node:_tls_wrap:1732:19)
  	at TLSSocket.emit (node:events:531:35)
  	at endReadableNT (node:internal/streams/readable:1698:12)
  	at process.processTicksAndRejections (node:internal/process/task_queues:90:21)

Connecting to https://copilot-proxy.githubusercontent.com/_ping:
- DNS ipv4 Lookup: 4.225.11.192 (19 ms)
- DNS ipv6 Lookup: Error (105 ms): getaddrinfo ENOTFOUND copilot-proxy.githubusercontent.com
- Proxy URL: None (25 ms)
- Electron fetch (configured): Error (51 ms): Error: net::ERR_CONNECTION_CLOSED
	at SimpleURLLoaderWrapper.<anonymous> (node:electron/js2c/utility_init:2:10684)
	at SimpleURLLoaderWrapper.emit (node:events:519:28)
  [object Object]
  {"is_request_error":true,"network_process_crashed":false}
- Node.js https: Error (107 ms): Error: Client network socket disconnected before secure TLS connection was established
	at TLSSocket.onConnectEnd (node:_tls_wrap:1732:19)
	at TLSSocket.emit (node:events:531:35)
	at endReadableNT (node:internal/streams/readable:1698:12)
	at process.processTicksAndRejections (node:internal/process/task_queues:90:21)
- Node.js fetch: Error (147 ms): TypeError: fetch failed
	at node:internal/deps/undici/undici:14900:13
	at process.processTicksAndRejections (node:internal/process/task_queues:105:5)
	at async n._fetch (c:\Users\Admin\.vscode\extensions\github.copilot-chat-0.37.1\dist\extension.js:4753:26157)
	at async n.fetch (c:\Users\Admin\.vscode\extensions\github.copilot-chat-0.37.1\dist\extension.js:4753:25805)
	at async d (c:\Users\Admin\.vscode\extensions\github.copilot-chat-0.37.1\dist\extension.js:4785:190)
	at async vA.h (file:///c:/Users/Admin/AppData/Local/Programs/Microsoft%20VS%20Code/bdd88df003/resources/app/out/vs/workbench/api/node/extensionHostProcess.js:116:41743)
  Error: Client network socket disconnected before secure TLS connection was established
  	at TLSSocket.onConnectEnd (node:_tls_wrap:1732:19)
  	at TLSSocket.emit (node:events:531:35)
  	at endReadableNT (node:internal/streams/readable:1698:12)
  	at process.processTicksAndRejections (node:internal/process/task_queues:90:21)

Connecting to https://mobile.events.data.microsoft.com: Error (42 ms): Error: net::ERR_CONNECTION_CLOSED
	at SimpleURLLoaderWrapper.<anonymous> (node:electron/js2c/utility_init:2:10684)
	at SimpleURLLoaderWrapper.emit (node:events:519:28)
  [object Object]
  {"is_request_error":true,"network_process_crashed":false}
Connecting to https://dc.services.visualstudio.com: Error (75 ms): Error: net::ERR_CONNECTION_CLOSED
	at SimpleURLLoaderWrapper.<anonymous> (node:electron/js2c/utility_init:2:10684)
	at SimpleURLLoaderWrapper.emit (node:events:519:28)
  [object Object]
  {"is_request_error":true,"network_process_crashed":false}
Connecting to https://copilot-telemetry.githubusercontent.com/_ping: Error (113 ms): Error: Client network socket disconnected before secure TLS connection was established
	at TLSSocket.onConnectEnd (node:_tls_wrap:1732:19)
	at TLSSocket.emit (node:events:531:35)
	at endReadableNT (node:internal/streams/readable:1698:12)
	at process.processTicksAndRejections (node:internal/process/task_queues:90:21)
Connecting to https://copilot-telemetry.githubusercontent.com/_ping: Error (127 ms): Error: Client network socket disconnected before secure TLS connection was established
	at TLSSocket.onConnectEnd (node:_tls_wrap:1732:19)
	at TLSSocket.emit (node:events:531:35)
	at endReadableNT (node:internal/streams/readable:1698:12)
	at process.processTicksAndRejections (node:internal/process/task_queues:90:21)
Connecting to https://default.exp-tas.com: Error (65 ms): Error: Client network socket disconnected before secure TLS connection was established
	at TLSSocket.onConnectEnd (node:_tls_wrap:1732:19)
	at TLSSocket.emit (node:events:531:35)
	at endReadableNT (node:internal/streams/readable:1698:12)
	at process.processTicksAndRejections (node:internal/process/task_queues:90:21)

Number of system certificates: 33

## Documentation

In corporate networks: [Troubleshooting firewall settings for GitHub Copilot](https://docs.github.com/en/copilot/troubleshooting-github-copilot/troubleshooting-firewall-settings-for-github-copilot).