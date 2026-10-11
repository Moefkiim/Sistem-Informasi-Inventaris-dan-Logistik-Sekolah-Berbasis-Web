@extends('errors.layout')

@section('title', '404 - Halaman Tidak Ditemukan')
@section('code', '404')
@section('code-color', 'text-primary')
@section('heading', 'Halaman Tidak Ditemukan')
@section('message', 'Halaman atau berkas yang Anda cari tidak dapat ditemukan pada sistem SIILS.')

@section('actions')
    <button type="button" onclick="if(window.history.length > 1){window.history.back();}else{window.location.href='{{ auth()->check() ? route('home') : route('login') }}';}" class="btn btn-light px-4">
        <i class="bi bi-arrow-left me-1"></i> Kembali
    </button>
@endsection
