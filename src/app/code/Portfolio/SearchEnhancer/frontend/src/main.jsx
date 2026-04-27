import React, { startTransition, useDeferredValue, useEffect, useId, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import './styles.css';

const DEFAULTS = {
  minQueryLength: 2,
  debounceMs: 250,
  maxCacheEntries: 50,
  cacheTtlMs: 60_000,
  requestTimeoutMs: 8_000,
};

function SearchAutocomplete({ endpoint, searchUrl, minQueryLength = DEFAULTS.minQueryLength }) {
  const [query, setQuery] = useState('');
  const [state, setState] = useState({ status: 'idle', items: [], message: '' });
  const [activeIndex, setActiveIndex] = useState(-1);
  const deferredQuery = useDeferredValue(query.trim());
  const abortRef = useRef(null);
  const cacheRef = useRef(new Map());
  const sequenceRef = useRef(0);
  const inputId = useId();
  const listboxId = `${inputId}-suggestions`;

  useEffect(() => {
    const normalizedQuery = deferredQuery.replace(/\s+/g, ' ');

    if (normalizedQuery.length < minQueryLength) {
      abortRef.current?.abort();
      setState({ status: 'idle', items: [], message: '' });
      setActiveIndex(-1);
      return;
    }

    const debounceTimer = window.setTimeout(() => {
      fetchSuggestions(normalizedQuery);
    }, DEFAULTS.debounceMs);

    return () => window.clearTimeout(debounceTimer);
  }, [deferredQuery, endpoint, minQueryLength]);

  async function fetchSuggestions(searchTerm) {
    const cacheKey = searchTerm.toLowerCase();
    const cached = getCached(cacheKey);

    if (cached) {
      startTransition(() => {
        setState({ status: 'success', items: cached.items, message: cached.items.length ? '' : 'No matching products found.' });
        setActiveIndex(cached.items.length ? 0 : -1);
      });
      return;
    }

    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    const requestSequence = ++sequenceRef.current;
    const timeoutId = window.setTimeout(() => controller.abort(), DEFAULTS.requestTimeoutMs);

    setState((current) => ({ ...current, status: 'loading', message: '' }));

    try {
      const url = new URL(endpoint, window.location.origin);
      url.searchParams.set('q', searchTerm);

      const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: controller.signal,
      });

      if (!response.ok) {
        throw new Error(`Suggestion request failed with HTTP ${response.status}`);
      }

      const payload = await response.json();

      if (requestSequence !== sequenceRef.current) {
        return;
      }

      const items = Array.isArray(payload.items) ? payload.items : [];
      setCached(cacheKey, items);

      startTransition(() => {
        setState({
          status: 'success',
          items,
          message: items.length ? '' : 'No matching products found.',
        });
        setActiveIndex(items.length ? 0 : -1);
      });
    } catch (error) {
      if (error.name === 'AbortError') {
        return;
      }

      if (requestSequence !== sequenceRef.current) {
        return;
      }

      setState({
        status: 'error',
        items: [],
        message: 'Search suggestions are temporarily unavailable.',
      });
      setActiveIndex(-1);
    } finally {
      window.clearTimeout(timeoutId);
    }
  }

  function getCached(cacheKey) {
    const entry = cacheRef.current.get(cacheKey);

    if (!entry) {
      return null;
    }

    if (Date.now() - entry.createdAt > DEFAULTS.cacheTtlMs) {
      cacheRef.current.delete(cacheKey);
      return null;
    }

    cacheRef.current.delete(cacheKey);
    cacheRef.current.set(cacheKey, entry);
    return entry;
  }

  function setCached(cacheKey, items) {
    cacheRef.current.set(cacheKey, { items, createdAt: Date.now() });

    while (cacheRef.current.size > DEFAULTS.maxCacheEntries) {
      const oldestKey = cacheRef.current.keys().next().value;
      cacheRef.current.delete(oldestKey);
    }
  }

  function handleKeyDown(event) {
    if (!state.items.length) {
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setActiveIndex((index) => (index + 1) % state.items.length);
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault();
      setActiveIndex((index) => (index - 1 + state.items.length) % state.items.length);
    }

    if (event.key === 'Enter' && activeIndex >= 0) {
      event.preventDefault();
      window.location.assign(state.items[activeIndex].url);
    }

    if (event.key === 'Escape') {
      setState({ status: 'idle', items: [], message: '' });
      setActiveIndex(-1);
    }
  }

  function submitSearch(event) {
    event.preventDefault();
    const trimmedQuery = query.trim();

    if (trimmedQuery.length >= minQueryLength) {
      const url = new URL(searchUrl, window.location.origin);
      url.searchParams.set('q', trimmedQuery);
      window.location.assign(url.toString());
    }
  }

  return (
    <form className="portfolio-search" role="search" onSubmit={submitSearch}>
      <label className="portfolio-search__label" htmlFor={inputId}>Enhanced Product Search</label>
      <div className="portfolio-search__control">
        <input
          id={inputId}
          className="portfolio-search__input"
          type="search"
          value={query}
          placeholder="Try backpack, watch, or headphones"
          autoComplete="off"
          aria-autocomplete="list"
          aria-controls={listboxId}
          aria-expanded={state.items.length > 0}
          aria-activedescendant={activeIndex >= 0 ? `${listboxId}-${activeIndex}` : undefined}
          onChange={(event) => setQuery(event.target.value)}
          onKeyDown={handleKeyDown}
        />
        <button className="portfolio-search__button" type="submit">Search</button>
      </div>

      <div className="portfolio-search__status" role="status" aria-live="polite">
        {state.status === 'loading' ? 'Loading suggestions...' : state.message}
      </div>

      {state.items.length > 0 && (
        <ul id={listboxId} className="portfolio-search__suggestions" role="listbox">
          {state.items.map((item, index) => (
            <li
              key={item.id}
              id={`${listboxId}-${index}`}
              role="option"
              aria-selected={activeIndex === index}
              className={activeIndex === index ? 'is-active' : ''}
            >
              <a href={item.url}>
                <span className="portfolio-search__name">{item.name}</span>
                <span className="portfolio-search__meta">{item.sku}{item.brand ? ` | ${item.brand}` : ''}</span>
              </a>
            </li>
          ))}
        </ul>
      )}
    </form>
  );
}

function mount() {
  const rootElement = document.querySelector('[data-portfolio-search-root]');

  if (!rootElement || rootElement.dataset.mounted === 'true') {
    return;
  }

  rootElement.dataset.mounted = 'true';
  createRoot(rootElement).render(
    <SearchAutocomplete
      endpoint={rootElement.dataset.endpoint}
      searchUrl={rootElement.dataset.searchUrl}
      minQueryLength={Number(rootElement.dataset.minQueryLength || DEFAULTS.minQueryLength)}
    />
  );
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount, { once: true });
} else {
  mount();
}
