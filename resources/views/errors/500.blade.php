@extends('errors.layout')

@section('title', '500 - Terjadi Kesalahan Server')
@section('code', '500')
@section('code-color', 'text-danger')
@section('heading', 'Terjadi Kendala Teknis')
@section('message', 'Terjadi kendala internal pada sistem. Tim pengelola telah mencatat masalah ini. Silakan coba kembali beberapa saat lagi.')

@section('actions')
    <button type="button" onclick="if(window.history.length > 1){window.history.back();}else{window.location.href='{{ auth()->check() ? route('home') : route('login') }}';}" class="btn btn-light px-4">
        <i class="bi bi-arrow-left me-1"></i> Kembali
    </button>
@endsection
