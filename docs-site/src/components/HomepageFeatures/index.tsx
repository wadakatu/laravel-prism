import type {ReactNode} from 'react';
import clsx from 'clsx';
import Heading from '@theme/Heading';
import styles from './styles.module.css';

type FeatureItem = {
  title: string;
  icon: string;
  description: ReactNode;
  gradient: string;
};

const FeatureList: FeatureItem[] = [
  {
    title: 'Zero Annotations',
    icon: '🚀',
    gradient: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    description: (
      <>
        No need to write PHPDoc annotations or maintain YAML files. 
        Laravel Spectrum analyzes your code and automatically generates 
        comprehensive API documentation.
      </>
    ),
  },
  {
    title: 'Smart Detection',
    icon: '🧠',
    gradient: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
    description: (
      <>
        Automatically detects FormRequests, validation rules, API Resources, 
        authentication methods, and response structures from your Laravel code.
      </>
    ),
  },
  {
    title: 'Real-time Updates',
    icon: '⚡',
    gradient: 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
    description: (
      <>
        Watch mode instantly reflects code changes in your documentation. 
        Perfect for development with live reload and WebSocket updates.
      </>
    ),
  },
  {
    title: 'Mock Server',
    icon: '🎭',
    gradient: 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
    description: (
      <>
        Built-in mock API server based on your OpenAPI documentation. 
        Frontend developers can start working immediately without backend.
      </>
    ),
  },
  {
    title: 'Export Anywhere',
    icon: '📤',
    gradient: 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
    description: (
      <>
        Export to Postman, Insomnia, or any OpenAPI-compatible tool. 
        Seamless integration with your existing API development workflow.
      </>
    ),
  },
  {
    title: 'Production Ready',
    icon: '🎯',
    gradient: 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)',
    description: (
      <>
        Optimized for large-scale projects with intelligent caching, 
        incremental generation, and excellent performance.
      </>
    ),
  },
];

function Feature({title, icon, description, gradient}: FeatureItem) {
  return (
    <div className={clsx('col col--4')}>
      <div className={styles.featureCard}>
        <div 
          className={styles.featureIcon}
          style={{background: gradient}}
        >
          <span className={styles.iconEmoji}>{icon}</span>
        </div>
        <div className="text--center padding-horiz--md">
          <Heading as="h3" className={styles.featureTitle}>{title}</Heading>
          <p className={styles.featureDescription}>{description}</p>
        </div>
      </div>
    </div>
  );
}

export default function HomepageFeatures(): ReactNode {
  return (
    <section className={styles.features}>
      <div className="container">
        <div className={styles.featuresHeader}>
          <Heading as="h2" className={styles.sectionTitle}>
            Why Choose Laravel Spectrum?
          </Heading>
          <p className={styles.sectionSubtitle}>
            The most intelligent API documentation generator for Laravel & Lumen
          </p>
        </div>
        <div className="row">
          {FeatureList.map((props, idx) => (
            <Feature key={idx} {...props} />
          ))}
        </div>
      </div>
    </section>
  );
}