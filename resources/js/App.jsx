import React from 'react';
import Header from './components/Header';
import Footer from './components/Footer';
import HeroSection from './components/HeroSection';
import ToolCard from './components/ToolCard';

const App = () => {
    const toolsData = [
        {
            category: 'documents',
            color: 'blue',
            title: 'Documents',
            icon: (
                <svg className="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            ),
            tools: [
                { name: 'Word to PDF', description: 'Convert DOCX to PDF' },
                { name: 'PDF to Word', description: 'Convert PDF to DOCX' },
                { name: 'Compress PDF', description: 'Reduce PDF size' }
            ]
        },
        {
            category: 'images',
            color: 'green',
            title: 'Images',
            icon: (
                <svg className="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            ),
            tools: [
                { name: 'JPG to PNG', description: 'Convert JPEG to PNG' },
                { name: 'PNG to JPG', description: 'Convert PNG to JPEG' },
                { name: 'Resize Image', description: 'Change dimensions' }
            ]
        },
        {
            category: 'audio',
            color: 'purple',
            title: 'Audio',
            icon: (
                <svg className="w-10 h-10 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                </svg>
            ),
            tools: [
                { name: 'MP3 to WAV', description: 'Convert MP3 to WAV' },
                { name: 'WAV to MP3', description: 'Convert WAV to MP3' },
                { name: 'Audio Trim', description: 'Cut audio files' }
            ]
        },
        {
            category: 'video',
            color: 'red',
            title: 'Video',
            icon: (
                <svg className="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
            ),
            tools: [
                { name: 'MP4 to AVI', description: 'Convert MP4 to AVI' },
                { name: 'Video Compress', description: 'Reduce video size' },
                { name: 'Extract Audio', description: 'Get audio from video' }
            ]
        }
    ];

    return (
        <div className="min-h-screen flex flex-col">
            <Header />
            <HeroSection />

            {/* Popular Tools Section */}
            <section id="tools-section" className="py-20 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center mb-16">
                        <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mb-4 animate-fade-in">Popular Tools</h2>
                        <p className="text-xl text-gray-600 animate-slide-up">Choose from our most used conversion tools</p>
                    </div>
                    
                    <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16">
                        {toolsData.map((tool, index) => (
                            <div key={tool.category} className="animate-slide-up" style={{ animationDelay: `${index * 0.1}s` }}>
                                <ToolCard {...tool} />
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <Footer />
        </div>
    );
};

export default App;
