# php-begin

## はじめに

最低限のコンポーネントを組み合わせる学習目的のサンプルです。  
実際のプロダクトでの使用に耐えるものでないことは承知ください。

記事内のPHPのコードでは`<?php`と`declare(strict_types=1);`を省略します。  

## 実行環境

Apache + PHP8.0以上 + Composerが動く環境を何とかして用意します。また、mod_rewriteが有効になっている必要があります。  
サンプルにDockerの設定ファイルを同梱しているので、サクッと試したい場合はそちらを使ってください。

### ディレクトリの作成

プロジェクトのディレクトリを作成します。  
以後、このディレクトリのパスをプロジェクトルートと呼びます。

```shell
mkdir php-basic
cd php-basic
```

Composerの初期化

```shell
composer init
```

composer.json

```JSON
{
  "name": "haikara/php-begin",
  "type": "project",
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  },
  "config": {
    "platform": {
      "php": "8.2"
    }
  }
}
```

いくつかライブラリを入れておきます。

```shell
composer require psr/http-message psr/container laminas/laminas-diactoros laminas/laminas-httphandlerrunner
```

- [psr/http-message](https://www.php-fig.org/psr/psr-7)  
- [psr/container](https://www.php-fig.org/psr/psr-11)  
- [laminas-diactoros](https://docs.laminas.dev/laminas-diactoros/)  
- [laminas-httphandlerrunner](https://docs.laminas.dev/laminas-httphandlerrunner/)


最低限必要になるファイルを作成します。

```shell
mkdir public
touch public/.htaccess public/index.php

mkdir bootstrap
touch bootstrap/app.php
```

- index.php  
  エントリーポイントとなるファイルです。すべてのリクエストはこのファイルを起点として処理されます。
- .htaccess  
  Apacheの設定ファイルです。リクエストをindex.phpに集めるようリライトの設定を記述します。

.htaccess

```.htaccess
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

.htaccessにはリライトの設定を記述します。  
ユーザーがリクエストしたファイル・ディレクトリが存在しなければ、代わりにindex.phpが呼び出されます。

index.php

```PHP
require_once __DIR__ . '/../bootstrap/app.php';
```

index.phpは、bootstrap/app.phpを呼び出すだけのファイルです。  
ここに色々と処理を記述することはしません。

app.php

```PHP
// オートローダーの読み込み
require_once __DIR__ . '/../vendor/autoload.php';

echo 'こんにちは世界！';
```

`autoload.php`というファイルを読み込んでいますが、これはオートローダーです。  
Composerでインストールしたパッケージを使うには、このファイルを読み込んでおく必要があります。  

ここで一度ブラウザを開いて、app.php内に記述したechoの内容がちゃんと表示されるか確認してみましょう。  
`http://localhost`などにアクセスして、「こんにちは世界」と表示されればOKです。  
もしエラーなどで出力されない場合は、以下のポイントをチェックしてみてください。

- リライトが有効になっているか
- パスが間違っていないか

## PSR-7 Request/Response

PHPはWeb向けの言語であり、HTTP関連の機能が豊富ですが、  
現代的なフレームワークではRequest/Responseを抽象化した機能を備えているため、  
PHP自体に備わっている機能を直接扱うことは稀だと思ってください。  

`$_GET`、`$_POST`などのスーパーグローバル変数はそのひとつです。    
これらのスーパーグローバル変数は、ユーザーのリクエストに含まれるパラメータを取り出した値が配列として入っていますが、   
現代的なフレームワークを用いた開発で使用することはありません。

また、特定のフレームワークへの依存を避けるための標準的な規格としてPSRというものもあり、
Request/Responseに関してはPSR-7として定義されています。  
PSR-7に準拠したライブラリは複数存在しますが、今回は`laminas/laminas-diactoros`を使用することとします。  

app.phpを以下のように変更します。

```PHP
// use宣言
use Laminas\Diactoros\ResponseFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

// オートローダーの読み込み
require_once __DIR__ . '/../vendor/autoload.php';

// Requestオブジェクトを作成
$request = ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);

// クエリパラメータを取り出す
$queryParams = $request->getQueryParams();
$name = (string)filter_var($queryParams['name'] ?? '世界');

$responseFactory = new ResponseFactory;

// Responseオブジェクトの作成
$response = $responseFactory->createResponse();
$response->getBody()->write("<p>こんにちは{$name}！</p>");

// レスポンスの送信
(new SapiEmitter)->emit($response);
```

ブラウザで表示を確認してみると変化はないはずですが、コードの記述は何だか複雑になりましたね。  
順に説明していきます。

#### use宣言

まずPHPの文法についてですが、`use`からはじまる行は名前空間をインポートしています。
たとえば`use Laminas\Diactoros\ResponseFactory`という部分は、    
「このファイルの中で`ResponseFactory`といえば`Laminas\Diactoros\ResponseFactory`のことだぞ」という宣言です。  
クラスを使うたびに完全修飾名（名前空間を含むフルネーム）を書くのは面倒ですが、use宣言をしておけば省略できるのです。

#### Requestオブジェクトの作成

`ServerRequestFactory::fromGlobals(...)`という部分で、Requestオブジェクトを作成しています。  
この中に`$_GET`や`$_POST`などに相当する情報が詰め込まれていると考えてください。  

#### クエリパラメータの取り出し
`$request->getQueryParams()`でURLに副m割れるクエリパラメータを配列として一括で取得しています。  
`getQueryParams`というメソッドはPSR-7のインターフェイスに定義されているため、  
PSR-7に準拠したライブラリであれば、どのライブラリでも同じように使えることが期待できます。

#### Responseオブジェクトの作成

`ResponseFactory`を通じて`Response`オブジェクトを作成しています。  
具体的には`$responseFactory->createResponse(200)`との記述がそれにあたります。

`$response->getBody()->write('こんにちは世界！')`という部分で、レスポンスのボディに文字列を書き込んでいます。  
いきなりechoで出力してしまうのではなく、`Response`に文字列を保持させているのです。
（実際はResponseオブジェクトが持つStreamオブジェクトが文字列を持っています）。

#### レスポンスの送信

`Response`に文字列を持たせただけでは、ブラウザには何も表示されません。  
実際にレスポンスを送信するための記述が`(new SapiEmitter)->emit($response)`という部分です。

echoするほうが簡単に見えますが、HTTPレスポンスはbody（目に見える部分）だけがすべてではありません。  
headerという部分にCookieなどの様々な情報を持っていて、実際のWeb開発ではそれらの情報も制御する必要があります。  
HTTPヘッダーの詳細をいま知る必要はありませんが、`SapiEmitter`が適切に処理してくれていることはおぼえておきましょう。

## ルーティング

いまのままだと、どんなURL（たとえはhttp://localhost/product/1）でアクセスされても、  
常にトップページが表示されてしまいます。すべてのリクエストをindex.phpに集めているから当然ですね。  

一般的なWebアプリケーションでは、パスの内容に応じてページを出し分けます。これをルーティングといいます。  
`/`にアクセスされたらトップページ、`/about`なら会社概要、`/product`なら商品一覧、といった具合です。

まさかそれらを愚直に条件分岐で書くわけにはいかないので、ルーティングライブラリを導入しましょう。  
今回はThe PHP Leagueの`league/route`を使います。  
[league/route](https://route.thephpleague.com)

```shell
composer require league/route
```

app.phpを以下のように書き換えます。

```PHP
// use宣言
use Laminas\Diactoros\ResponseFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

// オートローダーの読み込み
require_once __DIR__ . '/../vendor/autoload.php';

$router = new Router;

// ルーティング設定
$router->get('/', function (ServerRequestInterface $request) use ($responseFactory): ResponseInterface {
    $queryParams = $request->getQueryParams();
    $name = (string)filter_var($queryParams['name'] ?? '世界');

    $response = $responseFactory->createResponse();
    $response->getBody()->write("<p>こんにちは{$name}！</p>");

    return $response;
});

// 別のルートの設定
$router->get('/list', function (ServerRequestInterface $request) use ($responseFactory): ResponseInterface {
    $response = $responseFactory->createResponse();
    $response->getBody()->write("<p>何かの一覧画面</p>");

    return $response;
});

// Requestオブジェクトを作成
$request = ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);

// RequestオブジェクトをRouterに渡して、Responseオブジェクトを取得
$response = $router->dispatch($request);

// レスポンスの送信
(new SapiEmitter)->emit($response);
```

とりあえずブラウザでアクセスしてみてください。ただしく設定できていれば、
http://localhost なら「こんにちは世界！」、 http://localhost/list なら「何かの一覧画面」と表示されるはずです。

だいぶ見た目が変わったと思いますが、重要なポイントはふたつです。

- `$router->get`で各ルートを設定している。
- `$router->dispatch($request)`でRequestオブジェクトを渡し、Responseオブジェクトを受け取っている。

型も書いてあるので記述が長くなっていますが、ルーティング設定でやっていることは単純です。  
`Router:get`メソッドの第一引数にパスを指定し、第二引数に実行する処理を渡しています。  
「このパス（第一引数）にアクセスされたら、この処理（第二引数）を実行してね」という紐付けをしているのです。

この第一引数のことをルーティングパターン、第二引数のことをルーティングコールバックと言います。  
ルーティングコールバックは`ResponseInterface`の実装、つまり`Response`を返す必要があります。

```PHP
// この部分がルーティングコールバック
function (ServerRequestInterface $request) use ($responseFactory): ResponseInterface {
    /* ... */
    
    // 最終的にResponseを返すことが必須。
    return $response;
}
```

今回、ルーティングコールバックはクロージャをその場で書いて渡していますが、  
シグネチャを満たす`callable`型の値なら何でも渡すことが可能です。

たとえば`__invoke`メソッドを実装したクラスのオブジェクトを渡すようにすれば、  
具体的な処理を別のファイルに切り出せるようになります。  
このまますべての処理をapp.phpに書き連ねていくわけにはいかないので、さっそく試してみましょう。

### Actionクラス

```shell
mkdir -p app/Http/Action/Top
touch app/Http/Action/Top/TopAction.php
```

TopAction.php

```PHP
namespace App\Http\Action\Top;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TopAction {
    public function __construct(
        protected ResponseFactoryInterface $responseFactory
    ) {}
    
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        // クエリパラメータを取り出す
        $queryParams = $request->getQueryParams();
        $name = $queryParams['name'] ?? '世界';

        // ResponseにHTMLを書き込む
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("<p>こんにちは{$name}！</p>");

        // Responseを返す
        return $response;
    }
}
```

app.phpのルーティング設定の記述を変更。

```PHP
// 追加
use App\Http\Action\Top\TopAction;

// ルーティング設定を変更
$router->get('/', new TopAction($responseFactory));
```

`TopAction`は`__invoke`メソッドを持っているので関数として実行可能です。  
`__invoke`メソッドの返り値は`ResponseInterface`なので、ルーティングコールバックとして渡すことができます。

これでルーティングコールバックを別ファイルに切り出すことができました。  
`'/list'`のルーティングコールバックも同じように`Action`クラスを作って切り出してみてください。

## XSSとテンプレートエンジン

### 値のエスケープ

実はここまでのプログラムで、すでに脆弱性が生じてしまっています。  
問題はこの部分です。

```PHP
$response->getBody()->write("<p>こんにちは{$name}！</p>");
```

$nameの中身はユーザーが指定したクエリパラメータの値でした。  
しかし、ユーザーが自由に指定できる値をレスポンスボディ＝HTMLに埋め込むことは絶対にしてはいけません。  
XSSという攻撃が成立してしまうおそれがあります。どのような攻撃なのかは調べてみてください。

XSSを防ぐには`htmlspecialchars`という関数で値をエスケープします。

```PHP
$name = htmlspecialchars($queryParams['name'] ?? '世界', ENT_QUOTES, 'UTF-8');
```

長いです。この行だけならいいですが、実際のアプリケーションではもっとたくさんの値を扱います。  
優れたプログラマはラッパー関数を作って、短く書けるようにするものです。

```PHP
function h($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

$name = h($queryParams['name'] ?? '世界');
```

`htmlspecialchars`などという長ったらしい関数を何度も書く苦行から解放されましね。    
なんだかよく分からない引数たちも省略できるので、コードが短くなり、可読性が向上しました。  
これが巷で関数型プログラミングと呼ばれているテクニック――などということはありません。

まず、`ユーザーが指定した値かどうか`を人間が判断する時点で、エスケープし忘れるリスクがあります。  
であるならば、HTMLに埋め込む変数はすべてエスケープしてしまえばよいのです。

万全を期すため、デフォルトでエスケープしてくれるテンプレートエンジンを使いましょう。  
テンプレートエンジンのライブラリは有名なものがいくつかありますが、今回は`Twig`を採用します。  
[Twig](https://twig.symfony.com)

### Twig

twigをインストールし、必要なファイルを作成します。

```shell
composer require twig/twig

# テンプレートファイルの設置場所
mkdir -p resources/templates

# テンプレートファイルを作成
touch resources/templates/top.twig.html
```

app.phpを以下のように書き換えます。


```PHP
// use宣言
use Laminas\Diactoros\ResponseFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

// オートローダーの読み込み
require_once __DIR__ . '/../vendor/autoload.php';

// Requestオブジェクトを作成
$request = ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);

// Twigを呼び出し
$loader = new FilesystemLoader(__DIR__ . '/../resources/templates');
$twig = new Environment($loader);

// 第二引数に$twigを渡すよう変更
$router->get('/', new TopAction($responseFactory, $twig));

$response = $router->dispatch($request);

// レスポンスの送信
(new SapiEmitter)->emit($response);
```

TopAction.php

```PHP
namespace App\Http\Action\Top;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment as View; // 追加

class TopAction {
    public function __construct(
        protected ResponseFactoryInterface $responseFactory,
        protected View $view // 追加
    ) {}
    
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        // クエリパラメータを取り出す
        $queryParams = $request->getQueryParams();
        $name = $queryParams['name'] ?? '世界';
        
        // クエリパラメータをViewに渡してHTMLを生成
        $html = $this->view->render('top.twig.html', [
            'name' => $name,
        ]);

        // ResponseにHTMLを書き込む
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write($html);

        // Responseを返す
        return $response;
    }
}
```

top.twig.html

```html
<p>こんにちは{{ name }}！</p>
```

`{{ name }}`の部分に`$params['name']`の値が埋め込まれる仕組みです。  
Twigの独自構文は他にも`if`や`for`など色々あるので調べてみてください。

## Dependency InjectionとDIコンテナ

現状のルーティング設定には、実は大きな問題があります。  
ルーティングコールバックとして、Actionクラスをnewでインスタンス化してから渡している点です。  
どのルートにリクエストがあったかにかかわらず、すべてのActionクラスのコンストラクタが実行されてしまいます。  
トップページへのアクセスなのに、無関係なお問い合わせ画面のActionが実行されたりするとおかしいですよね。

これはパフォーマンス的にもよくありませんし、意図しない処理が実行されることで思わぬ副作用が生じかねません。  
限りなくバグに近い状態です。

### 良くない例

コンストラクタの引数に色々受け取っているのが問題かもしれません。  
`ResponseFactory`と`View`をわざわざ外から受け取るのをやめて、コンストラクタをなくし、  
`__invoke`メソッドの中でインスタンス化すればいいのではないでしょうか？

（下記のコードは実際に記述する必要はありません。）

```PHP
public function __invoke(ServerRequestInterface $request): ResponseInterface
{
    $queryParams = $request->getQueryParams();
    $name = $queryParams['name'] ?? '世界';
    
    // テンプレートファイルを置くディレクトリを指定して、Twigを呼び出し
    $loader = new FilesystemLoader(__DIR__ . '/../resources/templates/');
    $twig = new Environment($loader);
    
    $html = $view->render('top.twig.html', [
        'name' => $name,
    ]);

    // Responseを取得
    $responseFactory = new ResponseFactory;
    $response = $responseFactory->createResponse();
    
    // ResponseにHTMLを書き込む
    $response->getBody()->write($html);

    // Responseを返す
    return $response;
}
```

これですべてのActionクラスのコンストラクタが実行されてしまうことはなくなりますが、  
Actionクラスにコンストラクタを実装できないのは困りますし、  
すべてのActionクラスをインスタンス化してから渡している状況は変わりません。  
また、Actionクラスの中ではそのルートに関するロジックに集中したいのに、  
ライブラリのインスタンス生成処理が入り込んでいるのも煩雑です。

保守性の問題もあります。たとえば、テンプレートファイルの置き場所が変わったら？  
ResponseFactoryを別のライブラリの実装に入れ替えたくなったら？  
すべてのActionクラスの中身を書き換えて回るのは大変ですよね。 

そこで登場するのが`DIコンテナ`です。  
DIコンテナはオブジェクト同士の依存関係を管理し、ほしいものをいい感じに注入してくれる便利な箱です。  

### DIコンテナの導入

The PHP Leagueの`league/container`というDIコンテナがあるので、インストールしてみましょう。  
[league/container](https://container.thephpleague.com)

```shell
composer require league/container
touch bootstrap/dependencies.php
```

dependencies.php

```PHP
use App\Http\Action\Top\TopAction;
use Laminas\Diactoros\ResponseFactory;
use League\Container\Container;
use Psr\Http\Message\ResponseFactoryInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$container = new Container;

// ResponseFactoryInterfaceを要求されたら、ResponseFactoryのインスタンスを生成して渡すよう設定
$container->add(ResponseFactoryInterface::class, ResponseFactory::class);

// Environmentを要求されたら、生成処理を実行してその返り値（Environment）を渡すよう設定
$container->add(Environment::class, function () {
    $loader = new FilesystemLoader(__DIR__ . '/../resources/templates/');
    return new Environment($loader);
});

// TopActionのコンストラクタに何を渡すかを設定
$container->add(TopAction::class)
    ->addArgument(ResponseFactoryInterface::class)
    ->addArgument(Environment::class);

return $container;
```

app.php

```PHP
require_once __DIR__ . '/../vendor/autoload.php';

// 追加
$container = require_once __DIR__ . '/../bootstrap/dependencies.php';

// 追加
$strategy = new ApplicationStrategy;
$strategy->setContainer($container);
$router = new Router;
$router->setStrategy($strategy);

// 変更
$router->get('/', TopAction::class);
```

ルーティングコールバックにクラスの完全修飾名を渡せるようになりました。  
`TopAction::class`という部分がそうです。  

ルーティングパターンが一致したときに初めてクラスがインスタンス化され、そのオブジェクトが関数実行されます。  
これで関係ないクラスのインスタンス化処理が実行される心配はなくなりました。

ですが、新しくActionクラスを作るたびに、設定をdependencies.phpに追記するのでしょうか？  
そういう仕組みのフレームワークもありますが、やはり面倒です。

```PHP
// すべてのActionクラスについて、このような設定を書いていく？
$container->add(TopAction::class)
    ->addArgument(ResponseFactoryInterface::class)
    ->addArgument('View');
```

この問題をどうにかする方法を提供しているDIコンテナもあるのですが、  
シンプルさが売りの`league/container`にそのような機能はありません。  

気になる人は`PHP-DI`というライブラリを調べてみましょう。  
`Attributes`というPHPの言語機能を活用して、依存関係を解決する方法が備わっています。  
`PHP-DI`も`ContainerInterface`の実装なので、`league/container`と入れ替えるのは簡単です。

DIコンテナそのものの仕組みについては、こちらの記事で説明を試みているので参照ください。  
[AttributesでオートワイヤリングできるDIコンテナを作る](https://qiita.com/haikara/items/b68efefbfa252cdb3b2e)

---

このREADMEの内容と、実際のサンプルコードの内容は少し違う部分があります。  
何がどう違っていて、なぜそれで動いているのか、追いかけてみてください。
