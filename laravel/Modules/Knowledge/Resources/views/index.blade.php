@extends('layouts.app')
@section('title', 'ナレッジベース')
@section('header', 'ナレッジベース管理')

@section('content')
<div class="row">
    <div class="col-md-6">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">外部サイトの取り込み (クローリング)</h3>
            </div>
            <form action="{{ route('knowledge.crawl') }}" method="POST">
                @csrf
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    <div class="form-group">
                        <label for="url">ベースURL</label>
                        <input type="url" name="url" id="url" class="form-control" placeholder="https://manual.example.com/" required>
                        <small class="text-muted">※このURL配下の内部リンクを再帰的に巡回して取り込みます。</small>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">クローリング開始</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">RAG ステータス</h3>
            </div>
            <div class="card-body">
                <p>ベクターDB: <strong>PostgreSQL + pgvector</strong></p>
                <p>埋め込みモデル: <strong>Default</strong></p>
                <p>エンジン: <strong>LlamaIndex (Python)</strong></p>
            </div>
        </div>
    </div>
</div>
@endsection
