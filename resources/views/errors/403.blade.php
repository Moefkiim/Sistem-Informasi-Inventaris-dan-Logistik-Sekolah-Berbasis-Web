@extends('errors.layout')

@section('title', '403 - Akses Ditolak')
@section('code', '403')
@section('code-color', 'text-warning')
@section('heading', 'Akses Ditolak')
@section('message')
    {{ !empty($exception?->getMessage()) ? $exception->getMessage() : 'Akses ditolak: Anda tidak memiliki wewenang untuk membuka halaman ini.' }}
@endsection
